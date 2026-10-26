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
        ];
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
