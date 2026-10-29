<?php

namespace App\Services;

use App\Models\Dispositivo;
use App\Support\Sql;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Datos de la sección Firewalls: estado de cada FortiGate (lo que entrega su FORTINET-FORTIGATE-MIB)
 * y las listas de detalle (HA, túneles, SD-WAN, sensores, seguridad y alertas) de todos juntos.
 */
class FirewallsService
{
    /** Sin lectura del colector de detalle en este tiempo, sus datos se consideran viejos. */
    private const MINUTOS_DETALLE_VIGENTE = 15;
    /** Gráficas: últimas HORAS_HISTORIA horas, un punto cada BLOQUE_SEG segundos (promedio). */
    public const HORAS_HISTORIA = 6;
    private const BLOQUE_SEG = 300;
    /** Lectura de un puerto más vieja que esto ya no dice su estado actual. */
    private const MINUTOS_PUERTO_VIGENTE = 15;
    /** Enlace que el vecino no ha vuelto a ver en estos días: el cable pudo cambiar de lugar. */
    private const DIAS_ENLACE_VIGENTE = 2;

    public function resumen(): array
    {
        $alarmas = collect(AlarmDiagnosisService::diagnosticoCompartido());

        $fortis = Dispositivo::with(['telemetriaChasis', 'ultimaMetrica'])->get()
            ->filter(fn ($d) => $d->esFortigate())
            ->sortBy(fn ($d) => sprintf('%03d.%03d.%03d.%03d', ...array_map('intval', explode('.', (string) $d->ip) + [0, 0, 0, 0])))
            ->values();

        // Series y disponibilidad: consultas sobre metricas_red, compartidas un minuto entre pantallas
        $ids = $fortis->pluck('id')->all();
        $historia = Cache::remember('firewalls_historia_' . md5(implode(',', $ids)), 60, fn () => $this->historia($ids));
        // Máximo real de sesiones (de las muestras, no de los promedios de 5 min que usan las gráficas)
        $sesionesMax = Cache::remember('firewalls_sesiones_max_' . md5(implode(',', $ids)), 60, fn () => $ids ? DB::table('metricas_red')
            ->whereIn('dispositivo_id', $ids)->where('fecha_registro', '>=', now()->subHours(self::HORAS_HISTORIA))
            ->groupBy('dispositivo_id')->get(['dispositivo_id', DB::raw('MAX(conexiones_activas) AS maximo')])
            ->pluck('maximo', 'dispositivo_id')->all() : []);
        $disponibilidad = Cache::remember('firewalls_disponibilidad_' . md5(implode(',', $ids)), 60, fn () => $this->disponibilidad24h($ids));

        $filas = $fortis->map(fn ($d) => $this->fila($d, $alarmas->where('dispositivo_id', $d->id)) + [
            'historia' => $historia[$d->id] ?? [],
            'sesiones_max' => isset($sesionesMax[$d->id]) ? (int) $sesionesMax[$d->id] : null,
            'disponibilidad_24h' => $disponibilidad[$d->id] ?? null,
            'ping_ms' => $d->ultimaMetrica?->ping_ms,
            'perdida_pct' => $d->ultimaMetrica?->packet_loss,
        ]);
        // Interfaces con más uso, solo de los que responden SNMP
        $filas = $filas->map(fn ($f) => $f + ['interfaces' => $f['snmp'] ? $this->interfacesTop($f['id']) : []]);
        $conSnmp = $filas->where('snmp', true);

        $tuneles = $filas->flatMap(fn ($f) => collect($f['tuneles_lista'])->map(fn ($t) => $t + ['firewall' => $f['nombre'], 'id' => $f['id']]));
        $sdwan = $filas->flatMap(fn ($f) => collect($f['sdwan_lista'])->map(fn ($e) => $e + ['firewall' => $f['nombre'], 'id' => $f['id']]));
        $sensores = $filas->flatMap(fn ($f) => collect($f['sensores_lista'])->map(fn ($s) => $s + ['firewall' => $f['nombre'], 'id' => $f['id']]));
        $alarmasFw = $alarmas->whereIn('dispositivo_id', $fortis->pluck('id'))->values();
        $suma = fn (string $k) => $conSnmp->every(fn ($f) => $f[$k] === null) ? null : $conSnmp->sum(fn ($f) => $f[$k] ?? 0);

        return [
            'filas' => $filas->all(),
            'kpi' => [
                'total' => $filas->count(),
                'con_snmp' => $conSnmp->count(),
                'caidos' => $filas->where('estado', 'offline')->count(),
                'alarmas_criticas' => $alarmasFw->where('severidad', 'critica')->count(),
                'alarmas_advertencia' => $alarmasFw->where('severidad', 'advertencia')->count(),
                'sesiones' => $suma('sesiones'),
                'sesiones_nuevas_seg' => $suma('sesiones_nuevas_seg'),
                'tuneles_total' => $tuneles->count(),
                'tuneles_arriba' => $tuneles->where('estado', 'up')->count(),
                'sdwan_total' => $sdwan->count(),
                'sdwan_caidos' => $sdwan->where('estado', 'dead')->count(),
                'vpn_ssl_usuarios' => $suma('vpn_ssl_usuarios'),
                'ips_criticas_ciclo' => $suma('ips_criticas_ciclo'),
                // Lo que se mide por ping en los 14 (con o sin SNMP)
                'disponibilidad_24h' => $filas->whereNotNull('disponibilidad_24h')->avg('disponibilidad_24h'),
                'latencia_prom' => $filas->where('estado', '!=', 'offline')->whereNotNull('ping_ms')->avg('ping_ms'),
            ],
            'ha' => $filas->filter(fn ($f) => $f['ha'])->map(fn ($f) => ['firewall' => $f['nombre'], 'id' => $f['id']] + $f['ha'])->values()->all(),
            // Caídos primero; luego los de más tráfico
            'tuneles' => $tuneles->sortBy(fn ($t) => [$t['estado'] === 'up' ? 1 : 0, -(($t['mbps_in'] ?? 0) + ($t['mbps_out'] ?? 0))])->values()->all(),
            'sdwan' => $sdwan->sortBy(fn ($e) => [($e['estado'] ?? '') === 'dead' ? 0 : 1, -($e['perdida_pct'] ?? 0), -($e['latencia_ms'] ?? 0)])->values()->all(),
            'sensores_alarma' => $sensores->where('alarma', true)->values()->all(),
            'sensores_total' => $sensores->count(),
            'temperaturas' => $sensores->where('tipo', 'temperatura')->whereNotNull('valor')->sortByDesc('valor')->take(8)->values()->all(),
            'seguridad' => $conSnmp->filter(fn ($f) => $f['seguridad'])->map(fn ($f) => ['firewall' => $f['nombre'], 'id' => $f['id']] + $f['seguridad'])->values()->all(),
            'alarmas' => $alarmasFw->sortBy(fn ($a) => ($a['severidad'] ?? '') === 'critica' ? 0 : 1)->values()->all(),
            'sin_snmp' => $filas->where('snmp', false)->where('estado', '!=', 'offline')->values()->all(),
            'puertos' => $this->puertos($filas->all()),
        ];
    }

    /**
     * Puertos físicos de cada FortiGate, para dibujarlos. Del que responde SNMP: lo que reporta él mismo.
     * De los demás: solo los puertos que ven sus vecinos (LLDP/CDP), con el estado medido en el puerto del vecino,
     * que es el otro extremo del mismo cable. Nada se supone: sin lectura vigente, el estado queda en null.
     * [id => ['fuente' => 'snmp'|'vecinos'|null, 'grupos' => [[puerto, ...], ...], 'conectados' => n, 'total' => n]]
     */
    public function puertos(array $filas): array
    {
        $ids = array_column($filas, 'id');
        if (!$ids) {
            return [];
        }
        $vigente = now()->subMinutes(self::MINUTOS_PUERTO_VIGENTE);
        $enlaceVigente = now()->subDays(self::DIAS_ENLACE_VIGENTE);
        $campos = ['i.id', 'i.dispositivo_id', 'i.nombre', 'i.alias', 'i.port_type', 'i.mac_address', 'i.velocidad_mbps', 'i.admin_status',
            't.oper_status', 't.bandwidth_util_pct', 't.in_errors', 't.out_errors', 't.updated_at as leido'];
        // Estado y uso solo si la lectura es reciente (el poller los deja en NULL cuando el equipo no responde)
        $estado = fn ($x) => $x->leido && Carbon::parse($x->leido)->gte($vigente) ? $x->oper_status : null;
        $uso = fn ($x) => $estado($x) === 'up' && $x->bandwidth_util_pct !== null ? round((float) $x->bandwidth_util_pct, 2) : null;
        $velocidad = fn ($v) => $v ? ($v >= 1000 ? rtrim(rtrim(number_format($v / 1000, 1), '0'), '.') . ' Gbps' : $v . ' Mbps') : null;

        $interfaces = DB::table('interfaces_red as i')->leftJoin('telemetria_interfaces as t', 't.interfaz_id', '=', 'i.id')
            ->whereIn('i.dispositivo_id', $ids)->get($campos)->groupBy('dispositivo_id');

        // Enlaces descubiertos: interfaz del FortiGate => lo que hay del otro lado del cable
        $lados = [];
        $enlaces = DB::table('enlaces_red')
            ->where(fn ($q) => $q->whereIn('origen_dispositivo_id', $ids)->orWhereIn('destino_dispositivo_id', $ids))
            ->get(['origen_dispositivo_id', 'origen_interfaz_id', 'destino_dispositivo_id', 'destino_interfaz_id', 'tipo_medio', 'updated_at']);
        foreach ($enlaces as $e) {
            foreach ([['origen', 'destino'], ['destino', 'origen']] as [$aqui, $alla]) {
                if ($e->{"{$aqui}_interfaz_id"} && in_array($e->{"{$aqui}_dispositivo_id"}, $ids)) {
                    $lados[$e->{"{$aqui}_interfaz_id"}][] = ['interfaz' => $e->{"{$alla}_interfaz_id"}, 'equipo' => $e->{"{$alla}_dispositivo_id"},
                        'medio' => $e->tipo_medio, 'visto' => $e->updated_at];
                }
            }
        }
        $lejanas = collect($lados)->flatten(1);
        $otras = DB::table('interfaces_red as i')->leftJoin('telemetria_interfaces as t', 't.interfaz_id', '=', 'i.id')
            ->whereIn('i.id', $lejanas->pluck('interfaz')->filter()->unique()->values()->all())->get($campos)->keyBy('id');
        $equipos = DB::table('dispositivos')->whereIn('id', $lejanas->pluck('equipo')->unique()->values()->all())->pluck('nombre', 'id');

        $vecinosDe = fn (int $interfazId) => collect($lados[$interfazId] ?? [])->map(function ($l) use ($otras, $equipos, $estado, $uso, $enlaceVigente, $velocidad) {
            $o = $l['interfaz'] ? $otras->get($l['interfaz']) : null;
            $vigenteEnlace = $l['visto'] && Carbon::parse($l['visto'])->gte($enlaceVigente);
            return [
                'equipo_id' => $l['equipo'],
                'equipo' => $equipos[$l['equipo']] ?? 'Equipo #' . $l['equipo'],
                'puerto' => $o?->nombre,
                // Si el vecino dejó de verlo hace días, el estado de su puerto ya no dice nada de este cable
                'estado' => $o && $vigenteEnlace ? $estado($o) : null,
                'util_pct' => $o && $vigenteEnlace ? $uso($o) : null,
                'velocidad' => $o && $vigenteEnlace && $estado($o) === 'up' ? $velocidad($o->velocidad_mbps) : null,
                'medio' => $l['medio'],
                'visto_hace' => $vigenteEnlace || !$l['visto'] ? null : Carbon::parse($l['visto'])->locale('es')->diffForHumans(),
            ];
        })->unique(fn ($v) => $v['equipo_id'] . '|' . $v['puerto'])->values();

        $orden = ['mgmt' => 0, 'ha' => 0, 'wan' => 1, 'dmz' => 2, 'internal' => 3, 'port' => 4, 'lan' => 5, 's' => 6, 'vw' => 7, 'x' => 9];
        $resultado = [];
        foreach ($filas as $f) {
            $lista = collect($interfaces[$f['id']] ?? []);
            // Físicos: los que tienen MAC y no son virtuales (VLAN, túneles, agregados); sin SNMP, los que ve algún vecino
            $lista = $f['snmp']
                ? $lista->filter(fn ($i) => $i->port_type !== 'virtual' && $i->mac_address)
                : $lista->filter(fn ($i) => isset($lados[$i->id]));

            $puertos = $lista->map(function ($i) use ($f, $estado, $uso, $velocidad, $vecinosDe, $orden) {
                $vecinos = $vecinosDe($i->id);
                preg_match('/^([a-z]+)(\d*)$/i', $i->nombre, $m);
                $prefijo = $m ? strtolower($m[1]) : null;
                if ($f['snmp']) {
                    $e = $estado($i);
                    $p = [
                        'estado' => $i->admin_status === 'down' && $e !== 'up' ? 'deshabilitado' : $e,
                        'util_pct' => $uso($i),
                        'velocidad' => $e === 'up' ? $velocidad($i->velocidad_mbps) : null,
                        'errores' => $e !== null && $i->in_errors !== null ? (int) $i->in_errors + (int) $i->out_errors : null,
                        'tipo' => $i->port_type === 'sfp_fiber' ? 'sfp' : 'rj45',
                    ];
                } else {
                    // El estado del puerto es el del otro extremo del cable, medido en el vecino
                    $medidos = $vecinos->whereNotNull('estado');
                    $p = [
                        'estado' => $medidos->contains('estado', 'up') ? 'up' : ($medidos->isNotEmpty() ? 'down' : null),
                        'util_pct' => $medidos->whereNotNull('util_pct')->max('util_pct'),
                        'velocidad' => $medidos->whereNotNull('velocidad')->first()['velocidad'] ?? null,
                        'errores' => null,
                        // Sin SNMP el tipo de puerto no se conoce; solo el medio del enlace que reporta el vecino
                        'tipo' => $vecinos->contains(fn ($v) => str_starts_with((string) $v['medio'], 'fibra')) ? 'sfp' : 'generico',
                    ];
                }
                return $p + [
                    'clave' => $i->id,
                    'nombre' => $i->nombre,
                    'etiqueta' => $prefijo === 'port' && $m[2] !== '' ? $m[2] : $i->nombre,
                    'alias' => $i->alias,
                    'vecinos' => $vecinos->all(),
                    'grupo' => $prefijo === null ? 'otros' : (isset($orden[$prefijo]) && $orden[$prefijo] === 0 ? 'gestion' : $prefijo),
                    '_orden' => [$prefijo === null ? 8 : ($orden[$prefijo] ?? 8), $prefijo ?? $i->nombre, $m && $m[2] !== '' ? (int) $m[2] : 0],
                ];
            })->sortBy(fn ($p) => sprintf('%02d|%s|%05d', ...$p['_orden']))->values();

            $resultado[$f['id']] = [
                'fuente' => $puertos->isEmpty() ? null : ($f['snmp'] ? 'snmp' : 'vecinos'),
                'grupos' => $puertos->groupBy('grupo')->map(fn ($g) => $g->map(fn ($p) => collect($p)->except(['_orden', 'grupo'])->all())->values()->all())->values()->all(),
                'conectados' => $puertos->where('estado', 'up')->count(),
                'total' => $puertos->count(),
            ];
        }
        return $resultado;
    }

    /**
     * Promedio por bloque de 5 min de las últimas horas, por equipo:
     * [id => [['t' => epoch, 'cpu', 'ram', 'sesiones', 'mbps_in', 'mbps_out', 'ping'], ...]]
     */
    public function historia(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $bloque = 'FLOOR(' . Sql::epoch('fecha_registro') . ' / ' . self::BLOQUE_SEG . ') * ' . self::BLOQUE_SEG;
        $filas = DB::table('metricas_red')
            ->whereIn('dispositivo_id', $ids)
            ->where('fecha_registro', '>=', now()->subHours(self::HORAS_HISTORIA))
            ->groupBy('dispositivo_id', DB::raw($bloque))
            ->orderBy(DB::raw($bloque))
            ->get([
                'dispositivo_id', DB::raw("{$bloque} AS t"),
                DB::raw('AVG(cpu_usage) AS cpu'), DB::raw('AVG(memory_usage) AS ram'),
                DB::raw('AVG(conexiones_activas) AS sesiones'),
                DB::raw('AVG(traffic_in_mbps) AS mbps_in'), DB::raw('AVG(traffic_out_mbps) AS mbps_out'),
                DB::raw('AVG(ping_ms) AS ping'),
            ]);
        $r = fn ($v, $d = 1) => $v === null ? null : round((float) $v, $d);

        // fecha_registro es hora local sin zona: EPOCH la trata como UTC, así que gmdate devuelve la hora local tal cual
        return $filas->groupBy('dispositivo_id')->map(fn ($g) => $g->map(fn ($x) => [
            't' => (int) $x->t,
            'h' => gmdate('H:i', (int) $x->t),
            'cpu' => $r($x->cpu), 'ram' => $r($x->ram), 'sesiones' => $r($x->sesiones, 0),
            'mbps_in' => $r($x->mbps_in, 2), 'mbps_out' => $r($x->mbps_out, 2), 'ping' => $r($x->ping, 2),
        ])->values()->all())->all();
    }

    /** % de sondeos de las últimas 24 h en que el equipo respondió (ping o SNMP), por equipo. */
    public function disponibilidad24h(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        return DB::table('metricas_red')
            ->whereIn('dispositivo_id', $ids)
            ->where('fecha_registro', '>=', now()->subDay())
            ->groupBy('dispositivo_id')
            ->get(['dispositivo_id', DB::raw(Sql::contarSi('packet_loss IS NULL OR packet_loss < 100') . ' AS arriba'), DB::raw('COUNT(*) AS n')])
            ->mapWithKeys(fn ($x) => [$x->dispositivo_id => $x->n ? round($x->arriba / $x->n * 100, 2) : null])
            ->all();
    }

    /** Interfaces activas con más uso de ancho de banda (las que mueven el tráfico del firewall). */
    private function interfacesTop(int $dispositivoId, int $cuantas = 6): array
    {
        return DB::table('interfaces_red as i')
            ->join('telemetria_interfaces as t', 't.interfaz_id', '=', 'i.id')
            ->where('i.dispositivo_id', $dispositivoId)
            ->where('t.oper_status', 'up')
            ->whereNotNull('t.bandwidth_util_pct')
            ->orderByDesc('t.bandwidth_util_pct')
            ->limit($cuantas)
            ->get(['i.nombre', 'i.alias', 'i.velocidad_mbps', 't.bandwidth_util_pct'])
            ->map(fn ($x) => [
                'nombre' => $x->nombre,
                'alias' => $x->alias ?? null,
                'velocidad_mbps' => $x->velocidad_mbps,
                'util_pct' => (float) $x->bandwidth_util_pct,   // medido por el poller (mayor de entrada/salida contra ifHighSpeed)
            ])->all();
    }

    private function fila(Dispositivo $d, $alarmas): array
    {
        $c = $d->telemetriaChasis;
        $m = $d->ultimaMetrica;
        $snmp = $c && $c->uptime_str && !in_array($c->uptime_str, ['Sin respuesta SNMP', 'Inaccesible'], true);
        $detalleVigente = $c?->detalle_medido_en && $c->detalle_medido_en->gt(now()->subMinutes(self::MINUTOS_DETALLE_VIGENTE));
        // Lo que viene del colector de detalle solo se muestra si es reciente
        $det = fn ($v) => $snmp && $detalleVigente ? $v : null;

        $sistema = $det($c?->forti_sistema) ?? [];
        $ha = $det($c?->ha_estado);
        $tuneles = $det($c?->vpn_tuneles) ?? [];
        $sdwan = $det($c?->forti_sdwan) ?? [];
        $sensores = collect($det($c?->forti_sensores) ?? []);
        $ssl = $det($c?->forti_vpn_ssl);
        $seg = $det($c?->forti_seguridad);
        $temps = $sensores->where('tipo', 'temperatura')->whereNotNull('valor');

        return [
            'id' => $d->id,
            'nombre' => $d->nombre,
            'ip' => $d->ip,
            'estado' => $d->estado,
            'snmp' => $snmp,
            'version' => $sistema['version'] ?? null,
            'serie' => $sistema['serie'] ?? null,
            'uptime' => $snmp ? $c->uptime_str : null,
            'cpu' => $snmp ? $c->cpu_utilization : null,
            'ram' => $snmp ? $c->ram_utilization : null,
            'sesiones' => $snmp ? $m?->conexiones_activas : null,
            'sesiones_nuevas_seg' => $sistema['sesiones_nuevas_seg'] ?? null,
            'disco_pct' => $sistema['disco_pct'] ?? null,
            'ha' => is_array($ha) ? [
                'modo' => $ha['modo'] ?? null,
                'miembros' => $ha['miembros'] ?? [],
                'sincronizado' => empty($ha['miembros']) ? null : collect($ha['miembros'])->every(fn ($x) => $x['sincronizado'] ?? false),
            ] : null,
            'tuneles_lista' => $tuneles,
            'tuneles_total' => count($tuneles),
            'tuneles_arriba' => collect($tuneles)->where('estado', 'up')->count(),
            'sdwan_lista' => $sdwan,
            'sdwan_total' => count($sdwan),
            'sdwan_caidos' => collect($sdwan)->where('estado', 'dead')->count(),
            'sensores_lista' => $sensores->all(),
            'temp_max' => $temps->max('valor'),
            'vpn_ssl_usuarios' => $ssl['usuarios'] ?? null,
            'seguridad' => $seg,
            'ips_criticas_ciclo' => $seg['ips_criticas_ciclo'] ?? null,
            'alarmas' => $alarmas->count(),
            'alarmas_criticas' => $alarmas->where('severidad', 'critica')->count(),
            'detalle_hace' => $c?->detalle_medido_en ? Carbon::parse($c->detalle_medido_en)->diffForHumans() : null,
        ];
    }
}
