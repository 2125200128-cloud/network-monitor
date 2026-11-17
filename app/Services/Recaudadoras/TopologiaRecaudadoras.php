<?php

namespace App\Services\Recaudadoras;

use App\Console\Commands\RevisarRecaudadorasCommand;
use App\Models\Recaudadora;
use App\Models\RecaudadoraEquipo;
use Illuminate\Support\Collection;

/**
 * Las recaudadoras dentro del mapa de Topología (Red completa): por dónde llega cada sede al core y qué tiene adentro.
 *
 *   equipo del core por el que entra (último salto conocido de su traceroute, p. ej. GEJ-CORE01)
 *     └─ nube MPLS (una por punto de entrada real: «vía 10.13.128.14» o «saltos ocultos por el proveedor»)
 *          └─ FortiGate de la recaudadora (estado del módulo Recaudadoras)
 *               └─ kioscos, terminales de pago, antenas…
 *
 * La ruta la mide recaudadoras:rutas (cada 6 h). Si el FortiGate o un equipo ya están en el sondeo SNMP se usa su
 * nodo en vez de duplicarlo. Los nodos nuevos llevan id de texto (mpls-…, rc-…, rq-…) y su acomodo se guarda en el
 * mismo plano compartido. Sin posición guardada se colocan debajo del plano, por coordinadora.
 */
class TopologiaRecaudadoras
{
    /** Colores y estado de enlace iguales a los del mapa (TopologiaController::estiloEnlace). */
    private const ENLACE = [
        'UP' => ['#16a34a', 1, false, 0.95],
        'DEGRADED' => ['#f59e0b', 1, false, 1],
        'DOWN' => ['#ef4444', 1, [6, 4], 1],
        'UNKNOWN' => ['#64748b', 1, [4, 4], 0.9],
    ];
    private const IMAGEN_EQUIPO = [
        'kiosco' => 'images/topology/kiosco.svg', 'terminal' => 'images/topology/terminal-pago.svg',
        'antena' => 'images/topology/access-point.svg', 'ap' => 'images/topology/access-point.svg',
        'switch' => 'images/topology/switch-access.svg', 'modem' => 'images/topology/router.svg',
    ];

    /**
     * @param Collection $dispositivos equipos del sondeo (para enlazar por IP)
     * @param array $posiciones plano compartido (id => [x, y])
     * @return array{nodes: array, edges: array}
     */
    public function paraGrafo(Collection $dispositivos, array $posiciones): array
    {
        $recas = Recaudadora::with('equipos')->where('activo', true)
            ->where(fn ($q) => $q->whereNotNull('ip_administrativa')->orWhereNotNull('ip_loopback'))->get()
            ->sortBy(fn ($r) => [$this->ordenGrupo($r), (int) $r->numero, $r->nombre])->values();
        if ($recas->isEmpty()) {
            return ['nodes' => [], 'edges' => []];
        }
        $porIp = $dispositivos->filter(fn ($d) => $d->ip)->keyBy('ip');

        // Entrada de cada sede: último equipo conocido de su ruta y el primer salto desconocido después de él
        $entradas = [];
        foreach ($recas as $r) {
            $entradas[$r->id] = $this->entrada($r, $porIp);
        }
        // Sin ruta medida (o caída): la entrada más común. Si aún no se ha medido ninguna (recién importadas),
        // el core configurado en Ajustes, para que no queden sueltas mientras corre recaudadoras:rutas
        $comun = collect($entradas)->filter()->countBy(fn ($e) => $e['ancla'])->sortDesc()->keys()->first();
        $comunSalto = collect($entradas)->filter(fn ($e) => $e && $e['ancla'] === $comun)->countBy(fn ($e) => $e['salto'] ?? '')->sortDesc()->keys()->first();
        if (!$comun && ($ipCore = \App\Models\ConfiguracionGeneral::query()->value('ip_switch_core'))) {
            $comun = $porIp->get($ipCore)?->id;
        }

        $nodes = $edges = [];
        $nubes = [];      // clave => [ancla, salto, recas]
        $hijosDe = [];    // nodo de la sede => lista de equipos (para el acomodo)
        $sedes = [];      // id de nodo => recaudadora

        foreach ($recas as $r) {
            $propio = $porIp->get($r->ip_loopback) ?? $porIp->get($r->ip_administrativa);
            $e = $entradas[$r->id] ?? ($comun ? ['ancla' => $comun, 'salto' => $comunSalto ?: null, 'medida' => false] : null);
            if ($propio) {
                // El FortiGate ya está en el sondeo (p. ej. Reca 004 por fibra directa al core): su enlace sale por CDP
                $nodoSede = $propio->id;
            } else {
                $nodoSede = "rc-{$r->id}";
                $sedes[$nodoSede] = $r;
                if ($e) {
                    $clave = "mpls-{$e['ancla']}-" . ($e['salto'] ? str_replace('.', '-', $e['salto']) : 'oculto');
                    $nubes[$clave] ??= ['ancla' => $e['ancla'], 'salto' => $e['salto'], 'recas' => []];
                    $nubes[$clave]['recas'][] = $r;
                    $edges[] = $this->enlace("{$nodoSede}-e", $clave, $nodoSede, $this->estadoEnlaceSede($r),
                        'Red MPLS', $r->titulo(), $e['salto'] ?? 'MPLS', 'WAN', $this->tituloRuta($r, $porIp, $e['medida'] ?? true), $this->etiquetaEnlace($r));
                }
            }
            foreach ($r->equipos as $q) {
                $existente = $porIp->get($q->ip);
                $nodoEq = $existente ? $existente->id : "rq-{$q->id}";
                if (!$existente) {
                    $nodes[] = $this->nodoEquipo($q, $r, $posiciones);
                }
                $hijosDe[$nodoSede][] = $nodoEq;
                $edges[] = $this->enlace("rq-{$q->id}-e", $nodoSede, $nodoEq, $this->estadoEnlaceEquipo($q),
                    $propio ? $propio->nombre : $r->titulo(), $q->nombre, 'LAN', $q->ip,
                    "{$q->nombre} ({$q->ip}) en {$r->titulo()}\nEstado: " . $this->textoEquipo($q), '');
            }
        }

        foreach ($nubes as $clave => $n) {
            $ancla = $dispositivos->firstWhere('id', $n['ancla']);
            $caidas = collect($n['recas'])->filter(fn ($r) => $r->estadoVisible() === 'offline')->count();
            $nodes[] = $this->nodoNube($clave, $n, $ancla, $posiciones);
            $edges[] = $this->enlace("{$clave}-e", $n['ancla'], $clave, $caidas >= RevisarRecaudadorasCommand::CAIDA_MASIVA ? 'DEGRADED' : 'UP',
                $ancla?->nombre ?? 'Core', 'Red MPLS', 'Ruta a recaudadoras', $n['salto'] ?? 'MPLS',
                ($ancla?->nombre ?? 'Core') . ' → red MPLS de los proveedores' . ($n['salto'] ? " (primer salto {$n['salto']})" : ' (saltos ocultos por el proveedor)')
                . "\n" . count($n['recas']) . ' recaudadoras por aquí' . ($caidas ? " · {$caidas} sin comunicación" : ''), 'MPLS');
        }
        foreach ($sedes as $id => $r) {
            $nodes[] = $this->nodoSede($id, $r, $posiciones, $hijosDe[$id] ?? []);
        }

        $this->acomodar($nodes, $nubes, $hijosDe, $posiciones);
        $this->agregarConexiones($nodes, $edges);
        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /** ['ancla' => id del último equipo del sondeo en la ruta, 'salto' => primer salto desconocido después, 'medida' => bool] */
    private function entrada(Recaudadora $r, Collection $porIp): ?array
    {
        $saltos = $r->ruta['saltos'] ?? [];
        if (!$saltos || !($r->ruta['llego'] ?? false)) {
            return null;
        }
        $ancla = null;
        $despues = null;
        foreach (array_slice($saltos, 0, -1) as $h) {
            if ($h && $porIp->has($h)) {
                $ancla = $porIp->get($h)->id;
                $despues = null;
            } elseif ($ancla && $despues === null && $h) {
                $despues = $h;
            }
        }
        return $ancla ? ['ancla' => $ancla, 'salto' => $despues, 'medida' => true] : null;
    }

    // ===================== Nodos =====================

    private function nodoSede(string $id, Recaudadora $r, array $pos, array $hijos): array
    {
        $estado = $r->estadoVisible();
        $hace = $r->estado_desde && in_array($estado, ['offline', 'lan'], true)
            ? ' desde hace ' . RevisarRecaudadorasCommand::duracion((int) $r->estado_desde->diffInSeconds(now(), true)) : '';
        return $this->nodo($id, $r->titulo(), $r->ip_administrativa ?: $r->ip_loopback, 'images/topology/firewall-fortinet.svg', 95, $pos, [
            'rol' => 'RECAUDADORA · FORTIGATE',
            'tipo' => 'recaudadora',
            'tipo_equipo' => 'FortiGate de recaudadora',
            'estado' => $this->estadoNodo($estado),
            'estado_txt' => (Recaudadora::ESTADOS[$estado]['etiqueta'] ?? $estado) . $hace,
            'ping' => $r->latencia_ms !== null ? round($r->latencia_ms, 1) : null,
            'modelo' => trim(implode(' ', array_filter([$r->tipo_enlace, $r->uninet ? "Uninet {$r->uninet}" : null]))) ?: null,
            'factor_forma' => $r->referencia ? "Referencia del enlace: {$r->referencia}" : null,
            'vendor' => implode(' · ', $r->proveedores()) ?: null,
            'ubicacion' => trim(($r->municipio ?: '') . ($r->coordinadora ? ' · ' . (ctype_digit($r->coordinadora) ? 'Coordinadora ' . $r->coordinadora : $r->coordinadora) : ''), ' ·'),
            'url' => route('recaudadoras.show', $r->id),
            'ip_loopback' => $r->ip_loopback,
            'admin_ok' => $r->admin_ok,
            'loopback_ok' => $r->loopback_ok,
            'ruta' => $r->ruta['saltos'] ?? [],
            'ruta_medida' => $r->ruta_medida_en?->format('d/m H:i'),
            'equipos' => count($hijos),
        ]);
    }

    private function nodoEquipo(RecaudadoraEquipo $q, Recaudadora $r, array $pos): array
    {
        return $this->nodo("rq-{$q->id}", $q->nombre, $q->ip, self::IMAGEN_EQUIPO[$q->tipo] ?? 'images/topology/pc.svg', 60, $pos, [
            'rol' => mb_strtoupper(RecaudadoraEquipo::TIPOS[$q->tipo] ?? 'Equipo') . ' · RECAUDADORA',
            'tipo' => 'recaudadora_equipo',
            'tipo_equipo' => RecaudadoraEquipo::TIPOS[$q->tipo] ?? 'Equipo',
            'estado' => $this->estadoNodo($q->estadoVisible()),
            'estado_txt' => $this->textoEquipo($q),
            'ping' => $q->latencia_ms !== null ? round($q->latencia_ms, 1) : null,
            'modelo' => $q->notas,
            'ubicacion' => $r->titulo(),
            'url' => route('recaudadoras.show', $r->id),
        ]);
    }

    private function nodoNube(string $id, array $n, $ancla, array $pos): array
    {
        $caidas = collect($n['recas'])->filter(fn ($r) => $r->estadoVisible() === 'offline')->count();
        return $this->nodo($id, 'Red MPLS' . ($n['salto'] ? " · {$n['salto']}" : ' · saltos ocultos'), count($n['recas']) . ' recaudadoras',
            'images/topology/nube-mpls.svg', 120, $pos, [
                'rol' => 'NUBE MPLS · PROVEEDORES',
                'tipo' => 'mpls',
                'tipo_equipo' => 'Red MPLS de los proveedores (Uninet / Red Jalisco)',
                'estado' => $caidas >= RevisarRecaudadorasCommand::CAIDA_MASIVA ? 'warning' : 'online',
                'estado_txt' => count($n['recas']) . ' recaudadoras' . ($caidas ? " · {$caidas} sin comunicación" : ' · todas con comunicación'),
                'modelo' => $n['salto'] ? "Primer salto después de {$ancla?->nombre}: {$n['salto']}" : "El proveedor no muestra sus saltos: de {$ancla?->nombre} se llega directo a la sede",
                'ubicacion' => 'Entrada por ' . ($ancla?->nombre ?? 'el core'),
                'url' => route('recaudadoras.index'),
            ]);
    }

    /** Nodo con los mismos campos que los equipos del sondeo (el panel del mapa los usa). */
    private function nodo(string $id, string $nombre, ?string $ip, string $imagen, int $tamano, array $pos, array $extra): array
    {
        $guardada = isset($pos[$id]['x'], $pos[$id]['y']);
        $url = asset($imagen);
        return [
            'id' => $id,
            'label' => $nombre . "\n" . $ip,
            'title' => $nombre . ($ip ? " ({$ip})" : ''),
            'x' => $guardada ? (float) $pos[$id]['x'] : null,
            'y' => $guardada ? (float) $pos[$id]['y'] : null,
            'shape' => 'image',
            'image' => $url,
            'size' => $tamano,
            'borderWidth' => 0,
            'borderWidthSelected' => 0,
            'customData' => $extra + [
                'id' => $id, 'externo' => true, 'posicion_guardada' => $guardada, 'nombre' => $nombre, 'ip' => $ip,
                'statusColor' => match ($extra['estado'] ?? 'online') { 'online' => '#10b981', 'warning' => '#f26419', 'pendiente' => '#94a3b8', default => '#ef4444' },
                'image' => $url, 'cpu' => null, 'memoria' => null, 'serial' => null, 'uptime' => null, 'sysDescr' => '',
                'vendor' => $extra['vendor'] ?? null, 'conexiones' => [], 'notas' => 0, 'tipo_evidencia' => [], 'tipo_confianza' => 'manual',
                'tipo_fuente' => 'manual', 'tipo_manual' => null, 'sensores_temperatura' => [], 'pc_conectada' => null,
            ],
        ];
    }

    // ===================== Enlaces =====================

    private function enlace(string $id, $desde, $hasta, string $estado, string $origen, string $destino, string $pOrigen, string $pDestino, string $titulo, string $etiqueta): array
    {
        [$color, $ancho, $guiones, $opacidad] = self::ENLACE[$estado];
        return [
            'id' => $id, 'from' => $desde, 'to' => $hasta, 'label' => $etiqueta, 'title' => $titulo,
            'color' => ['color' => $color, 'highlight' => '#667d93', 'hover' => '#3b5998', 'opacity' => $opacidad],
            'width' => $ancho, 'dashes' => $guiones, 'smooth' => ['enabled' => false],
            'customData' => [
                'id' => $id, 'origen_id' => $desde, 'origen_disp' => $origen, 'origen_puerto' => $pOrigen, 'origen_puerto_abrev' => $pOrigen,
                'destino_id' => $hasta, 'destino_disp' => $destino, 'destino_puerto' => $pDestino, 'destino_puerto_abrev' => $pDestino,
                'tipo_medio' => str_starts_with($pDestino, 'LAN') || $pOrigen === 'LAN' ? 'RED INTERNA DE LA RECAUDADORA' : 'ENLACE WAN · MPLS',
                'velocidad_mbps' => null, 'estado' => $estado, 'color' => $color, 'ancho_px' => $ancho, 'dashes_px' => $guiones,
                'trafico_in' => null, 'trafico_out' => null, 'saturacion_pct' => null, 'vlans' => null, 'externo' => true, 'detalle' => $titulo,
            ],
        ];
    }

    /** Lista de cables de cada nodo nuevo, para el panel del mapa (la de los equipos del sondeo viene de CDP/LLDP). */
    private function agregarConexiones(array &$nodes, array $edges): void
    {
        $nombre = collect($nodes)->mapWithKeys(fn ($n) => [$n['id'] => [$n['customData']['nombre'], $n['customData']['ip']]]);
        $porNodo = [];
        foreach ($edges as $e) {
            foreach ([[$e['from'], $e['to'], 'origen', 'destino'], [$e['to'], $e['from'], 'destino', 'origen']] as [$aqui, $alla, $a, $b]) {
                $porNodo[$aqui][] = [
                    'enlace_id' => $e['id'], 'puerto_local' => $e['customData']["{$a}_puerto"], 'puerto_local_completo' => $e['customData']["{$a}_puerto"],
                    'remoto_id' => $alla, 'remoto_nombre' => $nombre[$alla][0] ?? $e['customData']["{$b}_disp"], 'remoto_ip' => $nombre[$alla][1] ?? '',
                    'puerto_remoto' => $e['customData']["{$b}_puerto"], 'puerto_remoto_completo' => $e['customData']["{$b}_puerto"],
                    'tipo_medio' => $e['customData']['tipo_medio'], 'velocidad' => '—', 'estado' => $e['customData']['estado'], 'color' => $e['customData']['color'],
                ];
            }
        }
        foreach ($nodes as &$n) {
            $n['customData']['conexiones'] = $porNodo[$n['id']] ?? [];
        }
    }

    // ===================== Acomodo inicial =====================

    /**
     * Solo para los nodos sin posición guardada: debajo de todo el plano (sin encimarse con lo existente), una nube
     * tras otra y sus sedes en columnas ordenadas por coordinadora, con sus equipos a la derecha.
     */
    private function acomodar(array &$nodes, array $nubes, array $hijosDe, array $posiciones): void
    {
        $faltan = collect($nodes)->filter(fn ($n) => $n['x'] === null)->pluck('id')->flip();
        if ($faltan->isEmpty() || !$posiciones) {
            return;   // plano vacío: el navegador acomoda todo con su árbol
        }
        $xs = array_column($posiciones, 'x');
        $ys = array_column($posiciones, 'y');
        $y0 = max($ys) + 2200;
        $x = min($xs);
        $nuevas = [];
        $POR_COLUMNA = 12;
        $ANCHO_COLUMNA = 1500;
        $FILA = 330;
        $ALTO_EQUIPO = 190;

        foreach ($nubes as $clave => $n) {
            $columnas = array_chunk($n['recas'], $POR_COLUMNA);
            $anchoNube = max(1, count($columnas)) * $ANCHO_COLUMNA;
            $nuevas[$clave] = ['x' => $x + $anchoNube / 2, 'y' => $y0];
            foreach ($columnas as $c => $lista) {
                $y = $y0 + 1100;
                foreach ($lista as $r) {
                    $id = "rc-{$r->id}";
                    $hijos = $hijosDe[$id] ?? [];
                    $alto = max($FILA, count($hijos) * $ALTO_EQUIPO);
                    $cx = $x + $c * $ANCHO_COLUMNA + 350;
                    $nuevas[$id] = ['x' => $cx, 'y' => $y + $alto / 2];
                    foreach ($hijos as $k => $h) {
                        $nuevas[$h] = ['x' => $cx + 520, 'y' => $y + $k * $ALTO_EQUIPO + $ALTO_EQUIPO / 2];
                    }
                    $y += $alto + 60;
                }
            }
            $x += $anchoNube + 1200;
        }
        foreach ($nodes as &$n) {
            if ($faltan->has($n['id']) && isset($nuevas[$n['id']])) {
                [$n['x'], $n['y']] = [$nuevas[$n['id']]['x'], $nuevas[$n['id']]['y']];
            }
        }
    }

    // ===================== Auxiliares =====================

    private function estadoNodo(string $estado): string
    {
        return match ($estado) { 'online' => 'online', 'lan' => 'warning', 'offline' => 'offline', default => 'pendiente' };
    }

    private function estadoEnlaceSede(Recaudadora $r): string
    {
        return match ($r->estadoVisible()) { 'online' => 'UP', 'lan' => 'DEGRADED', 'offline' => 'DOWN', default => 'UNKNOWN' };
    }

    private function estadoEnlaceEquipo(RecaudadoraEquipo $q): string
    {
        return match ($q->estadoVisible()) { 'online' => 'UP', 'offline' => 'DOWN', default => 'UNKNOWN' };
    }

    private function textoEquipo(RecaudadoraEquipo $q): string
    {
        return match ($q->estadoVisible()) {
            'online' => 'Responde' . ($q->latencia_ms !== null ? ' · ' . round($q->latencia_ms, 1) . ' ms' : ''),
            'offline' => 'Sin respuesta',
            'nunca' => 'Nunca ha respondido',
            default => 'Sin revisión reciente',
        };
    }

    private function etiquetaEnlace(Recaudadora $r): string
    {
        return (string) ($r->uninet ?: $r->velocidad_red_jalisco ?: '');
    }

    private function tituloRuta(Recaudadora $r, Collection $porIp, bool $medida): string
    {
        $saltos = $r->ruta['saltos'] ?? [];
        $ruta = $saltos ? implode(' → ', array_map(fn ($h) => $h ? ($porIp->has($h) ? $porIp->get($h)->nombre : $h) : '*', $saltos)) : null;
        return $r->titulo() . "\nEstado: " . (Recaudadora::ESTADOS[$r->estadoVisible()]['etiqueta'] ?? '')
            . "\nEnlace: " . (trim(implode(' ', array_filter([$r->tipo_enlace, $r->uninet ? "Uninet {$r->uninet}" : null, $r->referencia ? "ref. {$r->referencia}" : null]))) ?: 'sin dato')
            . "\nRuta" . ($medida && $r->ruta_medida_en ? ' medida ' . $r->ruta_medida_en->format('d/m H:i') : ' (sin medir: se usa la entrada más común)') . ': ' . ($ruta ?? '—');
    }

    private function ordenGrupo(Recaudadora $r): int
    {
        return match (true) {
            $r->tipo !== 'recaudadora' => 300,
            ctype_digit((string) $r->coordinadora) => (int) $r->coordinadora,
            $r->coordinadora === 'Metropolitano' => 100,
            default => 200,
        };
    }
}
