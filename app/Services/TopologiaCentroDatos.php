<?php

namespace App\Services;

use App\Models\InventarioCentroDatos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Topología del centro de datos: el inventario físico (inventario_centro_datos) agrupado en nodos lógicos
 * (equipo, par HA o stack), con el estado en vivo del monitoreo (por IP) y solo los enlaces descubiertos
 * por CDP/LLDP (enlaces_red). Un equipo que no está en el monitoreo se muestra como tal, sin enlaces supuestos.
 */
class TopologiaCentroDatos
{
    public const CAPAS = [
        'perimetro' => ['titulo' => 'Perímetro y seguridad', 'esperado' => ['firewall']],
        'balanceo' => ['titulo' => 'Balanceo de carga', 'esperado' => ['balanceador', 'servidor', 'desconocido']],
        'core' => ['titulo' => 'Núcleo (core)', 'esperado' => ['switch', 'router']],
        'agregacion' => ['titulo' => 'Distribución y servidores', 'esperado' => ['switch']],
        'acceso' => ['titulo' => 'Acceso', 'esperado' => ['switch']],
        'inalambrico' => ['titulo' => 'Inalámbrico', 'esperado' => ['wlc']],
    ];

    /** Franjas del diagrama (de arriba abajo); agrupan capas para que todo quepa en una pantalla de 1080 px. */
    public const FRANJAS = [
        'borde' => ['titulo' => 'Perímetro y balanceo', 'capas' => ['perimetro', 'balanceo']],
        'core' => ['titulo' => 'Núcleo (core)', 'capas' => ['core']],
        'agregacion' => ['titulo' => 'Distribución y servidores', 'capas' => ['agregacion']],
        'acceso' => ['titulo' => 'Acceso e inalámbrico', 'capas' => ['acceso', 'inalambrico']],
    ];

    public function datos(): array
    {
        $filas = InventarioCentroDatos::orderBy('orden')->orderBy('id')->get();
        $ips = $filas->pluck('ip')->filter()->unique()->values();

        // Se cruza por IP de gestión y, si no está, por hostname: un equipo puede estar monitoreado por otra de sus IP
        // (p. ej. la que anuncia por CDP)
        $identidad = fn (?string $nombre) => $nombre ? strtolower(explode('.', preg_replace('/\(.*?\)/', '', $nombre))[0]) : '';
        $hostnames = $filas->pluck('hostname')->filter()->map($identidad)->unique()->values();
        $primerSegmento = \App\Support\Sql::esPgsql() ? "SPLIT_PART(d.nombre, '.', 1)" : "SUBSTRING_INDEX(d.nombre, '.', 1)";
        $disp = DB::table('dispositivos as d')
            ->leftJoin('telemetria_chasis as t', 't.dispositivo_id', '=', 'd.id')
            ->where(fn ($q) => $q->whereIn('d.ip', $ips)->orWhereIn(DB::raw("LOWER({$primerSegmento})"), $hostnames))
            ->get(['d.id', 'd.nombre', 'd.ip', 'd.estado', 'd.tipo_detectado', 'd.modelo_detectado', 'd.cpu_usage', 'd.memoria_usage',
                'd.ultima_vez_visto', 't.uptime_str', 't.model_name', 't.cpu_utilization', 't.ram_utilization']);
        $porIp = $disp->keyBy('ip');
        $porNombre = $disp->keyBy(fn ($d) => $identidad($d->nombre));
        $buscar = fn (Collection $miembros) => $porIp->get($miembros->first()->ip)
            ?? $miembros->pluck('hostname')->filter()->map(fn ($h) => $porNombre->get($identidad($h)))->filter()->first();

        $nodos = $filas->groupBy('nodo')->map(fn (Collection $miembros, string $nodo) => $this->nodo($nodo, $miembros, $buscar($miembros)));
        $porDispositivo = $nodos->filter(fn ($n) => $n['dispositivo_id'])->mapWithKeys(fn ($n) => [$n['dispositivo_id'] => $n['id']]);

        [$enlaces, $externos] = $this->enlaces($porDispositivo);
        $nodos = $nodos->map(function ($n) use ($externos) {
            $n['enlaces_externos'] = $externos[$n['id']] ?? 0;
            return $n;
        });

        $capas = collect(self::FRANJAS)->map(fn ($f, $clave) => [
            'id' => $clave,
            'titulo' => $f['titulo'],
            'nodos' => collect($f['capas'])->flatMap(fn ($capa) => $nodos->where('capa', $capa)->sortBy('orden')->pluck('id'))->values(),
        ])->filter(fn ($c) => $c['nodos']->isNotEmpty())->values();

        $monitoreados = $nodos->whereNotNull('dispositivo_id');
        return [
            'capas' => $capas,
            'nodos' => $nodos->values(),
            'enlaces' => $enlaces,
            'resumen' => [
                'equipos_fisicos' => $filas->count(),
                'nodos' => $nodos->count(),
                'monitoreados' => $monitoreados->count(),
                'online' => $monitoreados->where('estado', 'online')->count(),
                'warning' => $monitoreados->where('estado', 'warning')->count(),
                'offline' => $monitoreados->where('estado', 'offline')->count(),
                'sin_monitoreo' => $nodos->whereNull('dispositivo_id')->count(),
                'enlaces' => count($enlaces),
                'actualizado' => now()->format('H:i:s'),
            ],
        ];
    }

    private function nodo(string $nodo, Collection $miembros, ?object $d): array
    {
        $p = $miembros->first();
        $tipo = $miembros->count() > 1 ? ($miembros->every(fn ($m) => $m->rol === 'miembro') ? 'stack' : 'ha') : 'unico';
        $versionSnmp = $d && preg_match('/Version\s+([^,\s]+)/i', (string) $d->model_name, $m) ? $m[1] : null;

        $avisos = [];
        if ($d) {
            $esperado = self::CAPAS[$p->capa]['esperado'];
            if ($d->tipo_detectado && !in_array($d->tipo_detectado, $esperado, true)) {
                $avisos[] = "El monitoreo lo clasifica como «{$d->tipo_detectado}» y el inventario dice {$p->marca} {$p->modelo} ({$p->descripcion}).";
            }
            $versionInv = $miembros->pluck('version_os')->filter()->first();
            if ($versionInv && $versionSnmp && $versionInv !== $versionSnmp) {
                $avisos[] = "Versión en inventario {$versionInv}, el equipo reporta {$versionSnmp}.";
            }
        }

        $cpu = $d ? ($d->cpu_utilization ?? $d->cpu_usage) : null;
        $ram = $d ? ($d->ram_utilization ?? $d->memoria_usage) : null;

        return [
            'id' => $nodo,
            'capa' => $p->capa,
            'capa_titulo' => self::CAPAS[$p->capa]['titulo'],
            'orden' => $p->orden,
            'tipo' => $tipo,
            'titulo' => $miembros->pluck('hostname')->filter()->unique()->implode(' / ') ?: ($d->nombre ?? "{$p->marca} {$p->modelo}"),
            'marca' => $p->marca,
            'modelo' => $miembros->pluck('modelo')->unique()->implode(' · '),
            'descripcion' => $miembros->pluck('descripcion')->filter()->unique()->first(),
            'ip' => $p->ip,
            'arte' => $this->arte($p->modelo),
            'miembros' => $miembros->map(fn ($m) => [
                'rol' => $m->rol, 'modelo' => $m->modelo, 'serie' => $m->serie, 'hostname' => $m->hostname,
                'descripcion' => $m->descripcion, 'version' => $m->version_os,
            ])->values(),
            'dispositivo_id' => $d->id ?? null,
            'nombre_monitoreo' => $d->nombre ?? null,
            'estado' => $d->estado ?? null,
            'tipo_detectado' => $d->tipo_detectado ?? null,
            'cpu' => $cpu !== null ? (float) $cpu : null,
            'ram' => $ram !== null ? (float) $ram : null,
            'uptime' => $d->uptime_str ?? null,
            'version_snmp' => $versionSnmp,
            'ultimo_contacto' => $d && $d->ultima_vez_visto ? \Carbon\Carbon::parse($d->ultima_vez_visto)->locale('es')->diffForHumans() : null,
            'ficha' => $d ? route('dispositivos.show', $d->id) : null,
            'avisos' => $avisos,
        ];
    }

    /**
     * Enlaces descubiertos entre equipos del centro de datos, uno por par de nodos (los paralelos se cuentan),
     * y cuántos enlaces tiene cada nodo hacia equipos fuera de esta vista.
     */
    private function enlaces(Collection $porDispositivo): array
    {
        $ids = $porDispositivo->keys()->all();
        if (!$ids) {
            return [[], []];
        }

        $filas = DB::table('enlaces_red as e')
            ->leftJoin('interfaces_red as io', 'io.id', '=', 'e.origen_interfaz_id')
            ->leftJoin('interfaces_red as idst', 'idst.id', '=', 'e.destino_interfaz_id')
            ->leftJoin('telemetria_interfaces as to', 'to.interfaz_id', '=', 'e.origen_interfaz_id')
            ->leftJoin('telemetria_interfaces as tdst', 'tdst.interfaz_id', '=', 'e.destino_interfaz_id')
            ->leftJoin('dispositivos as dor', 'dor.id', '=', 'e.origen_dispositivo_id')
            ->leftJoin('dispositivos as dde', 'dde.id', '=', 'e.destino_dispositivo_id')
            ->where(fn ($q) => $q->whereIn('e.origen_dispositivo_id', $ids)->orWhereIn('e.destino_dispositivo_id', $ids))
            ->get(['e.id', 'e.origen_dispositivo_id', 'e.destino_dispositivo_id', 'e.tipo_medio', 'e.velocidad_mbps',
                'e.trafico_in_mbps', 'e.trafico_out_mbps', 'io.nombre as if_origen', 'idst.nombre as if_destino',
                'to.oper_status as oper_origen', 'tdst.oper_status as oper_destino', 'dor.estado as estado_origen', 'dde.estado as estado_destino']);

        $pares = [];
        $externos = [];
        foreach ($filas as $f) {
            $a = $porDispositivo[$f->origen_dispositivo_id] ?? null;
            $b = $porDispositivo[$f->destino_dispositivo_id] ?? null;
            if (!$a || !$b) {
                $propio = $a ?? $b;
                $externos[$propio] = ($externos[$propio] ?? 0) + 1;
                continue;
            }
            if ($a === $b) {
                continue;
            }
            [$x, $y, $ifx, $ify] = strcmp($a, $b) < 0
                ? [$a, $b, $f->if_origen, $f->if_destino]
                : [$b, $a, $f->if_destino, $f->if_origen];
            $k = "{$x}|{$y}";
            $pares[$k] ??= ['desde' => $x, 'hasta' => $y, 'cantidad' => 0, 'estado' => 'up', 'velocidad_mbps' => 0, 'trafico_mbps' => 0.0, 'puertos' => []];
            $p = &$pares[$k];
            $p['cantidad']++;
            $p['velocidad_mbps'] += (int) $f->velocidad_mbps;
            $p['trafico_mbps'] += (float) $f->trafico_in_mbps + (float) $f->trafico_out_mbps;
            $p['estado'] = $this->peorEstado($p['estado'], $this->estadoEnlace($f));
            $p['puertos'][] = [$this->interfaz($ifx), $this->interfaz($ify)];
            unset($p);
        }

        return [array_values(array_map(function ($p) {
            $p['trafico_mbps'] = round($p['trafico_mbps'], 2);
            return $p;
        }, $pares)), $externos];
    }

    /** Misma regla que la topología general: puertos SNMP de cada extremo y, sin ellos, el estado de los equipos. */
    private function estadoEnlace(object $f): string
    {
        if ($f->oper_origen === 'down' || $f->oper_destino === 'down' || $f->estado_origen === 'offline' || $f->estado_destino === 'offline') {
            return 'down';
        }
        if ($f->oper_origen === 'up' || $f->oper_destino === 'up') {
            return ($f->estado_origen === 'warning' || $f->estado_destino === 'warning') ? 'degraded' : 'up';
        }
        return 'unknown';
    }

    private function peorEstado(string $actual, ?string $nuevo): string
    {
        $orden = ['down' => 3, 'degraded' => 2, 'unknown' => 1, 'up' => 0];
        $nuevo = $nuevo && isset($orden[$nuevo]) ? $nuevo : 'unknown';
        return $orden[$nuevo] > $orden[$actual] ? $nuevo : $actual;
    }

    /** Nombre de interfaz legible; los que el colector armó con el ifIndex (p. ej. GigabitEthernet437276672) no lo son. */
    private function interfaz(?string $nombre): ?string
    {
        return $nombre && !preg_match('/\d{7,}$/', $nombre) ? $nombre : null;
    }

    /** Dibujo del panel frontal según el número de parte del inventario (null si el modelo no se reconoce). */
    private function arte(string $modelo): ?string
    {
        $parte = strtoupper($modelo);
        $parte = preg_replace('/^C1-/', '', $parte);                            // kits C1- de Cisco ONE
        $parte = preg_replace('/^N70(\d{2})-.*/', 'N7K-C70$1', $parte);         // C1-N7004-S2-R → N7K-C7004
        $parte = preg_replace('/^FORTIGATE\s+(\d+[A-Z])$/', 'FORTIGATE-$1', $parte);
        $parte = preg_replace('/\s+-(\w)\b/', '-$1', $parte);                   // "WS-C3750-24PS -S" → "WS-C3750-24PS-S"
        $parte = preg_replace('/\s+V\d+$/', '', $parte);                        // sufijo de revisión de hardware

        $spec = DeviceArtwork::especificacion($parte);
        return $spec ? asset(DeviceArtwork::asegurarArchivo($spec)['image']) : null;
    }
}
