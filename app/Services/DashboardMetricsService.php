<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Métricas del dashboard calculadas solo con datos medidos (snmp_poller, snmp_detail_collector,
 * web:poll y noc:sync). Cuando una métrica no tiene datos se devuelve null / lista vacía,
 * y la vista lo muestra como "Sin dato".
 */
class DashboardMetricsService
{
    /** Utilización ≥ este % se considera saturación (igual que snmp_poller.SATURATION_PCT). */
    public const SATURACION_PCT = 80;
    /** Potencia óptica Rx por debajo de este valor (dBm) en un puerto activo se reporta como baja. */
    public const RX_BAJO_DBM = -15;
    /** Valor mínimo que reportan los sensores DOM cuando no hay lectura. */
    public const RX_PISO_DBM = -39.9;
    /** Antigüedad máxima (s) de una lectura de puerto para considerarla vigente. */
    private const VIGENCIA_PUERTO_S = 600;

    public function paneles(): array
    {
        return Cache::remember('dashboard_paneles_v1', 60, fn () => [
            'puertos' => $this->puertos(),
            'hardware' => $this->hardware(),
            'top_equipos' => $this->topEquipos(),
            'disponibilidad' => Cache::remember('dashboard_sla_v1', 300, fn () => $this->disponibilidad()),
            'servicios_web' => $this->serviciosWeb(),
            'incidentes' => $this->incidentes(),
            'cobertura' => $this->cobertura(),
            'endpoints' => Cache::remember('dashboard_endpoints_v1', 300, fn () => $this->endpoints()),
            'enlaces' => $this->enlaces(),
            'seguridad' => $this->seguridad(),
            'trafico' => $this->tendenciaTrafico(),
        ]);
    }

    // =====================================================================
    //  PUERTOS
    // =====================================================================
    public function puertos(): array
    {
        $vigente = now()->subSeconds(self::VIGENCIA_PUERTO_S);
        $rows = DB::table('interfaces_red as i')
            ->join('dispositivos as d', 'd.id', '=', 'i.dispositivo_id')
            ->leftJoin('telemetria_interfaces as t', 't.interfaz_id', '=', 'i.id')
            ->whereIn('i.port_type', ['copper_rj45', 'sfp_fiber'])
            ->where('d.estado', '!=', 'offline')
            ->get([
                'i.id', 'i.nombre', 'i.alias', 'i.admin_status', 'i.velocidad_mbps', 'i.duplex', 'i.port_type',
                'd.id as disp_id', 'd.nombre as disp',
                't.oper_status', 't.bandwidth_util_pct', 't.in_errors', 't.out_errors', 't.in_discards',
                't.out_discards', 't.crc_errors', 't.broadcast_pps', 't.is_errdisabled', 't.errdisabled_reason',
                't.optica_rx_dbm', 't.optica_tx_dbm', 't.updated_at',
            ]);

        $enEnlace = DB::table('enlaces_red')->pluck('origen_interfaz_id')
            ->merge(DB::table('enlaces_red')->pluck('destino_interfaz_id'))
            ->filter()->flip();

        // Estado operativo solo si la lectura es reciente
        $rows->each(function ($r) use ($vigente, $enEnlace) {
            $r->oper = $r->updated_at && Carbon::parse($r->updated_at)->gte($vigente) ? $r->oper_status : null;
            $r->en_enlace = $enEnlace->has($r->id);
            $r->con_uso = $r->en_enlace || trim((string) $r->alias) !== '';
            $r->err_total = $r->in_errors === null && $r->out_errors === null ? null : (int) $r->in_errors + (int) $r->out_errors;
            $r->disc_total = $r->in_discards === null && $r->out_discards === null ? null : (int) $r->in_discards + (int) $r->out_discards;
        });

        $fmt = fn ($r, array $extra = []) => [
            'disp_id' => $r->disp_id, 'dispositivo' => $r->disp, 'puerto' => $r->nombre,
            'alias' => $r->alias ?: null,
        ] + $extra;

        $up = $rows->where('oper', 'up');
        $caidosConUso = $rows->filter(fn ($r) => $r->admin_status === 'up' && $r->oper === 'down' && $r->con_uso && !$r->is_errdisabled);

        return [
            'total' => $rows->count(),
            'up' => $up->count(),
            'down' => $rows->where('oper', 'down')->count(),
            'admin_down' => $rows->where('admin_status', 'down')->count(),
            'libres' => $rows->filter(fn ($r) => $r->admin_status === 'up' && $r->oper === 'down' && !$r->con_uso)->count(),
            'sin_dato' => $rows->whereNull('oper')->count(),
            'err_disabled' => $rows->filter(fn ($r) => (bool) $r->is_errdisabled)
                ->map(fn ($r) => $fmt($r, ['motivo' => $r->errdisabled_reason ?: 'No reportado']))->values()->all(),
            'caidos_con_uso_total' => $caidosConUso->count(),
            'caidos_con_uso' => $caidosConUso->sortByDesc('en_enlace')->take(12)
                ->map(fn ($r) => $fmt($r, ['enlace' => $r->en_enlace]))->values()->all(),
            'saturados' => $up->filter(fn ($r) => $r->bandwidth_util_pct !== null && $r->bandwidth_util_pct > 0)
                ->sortByDesc(fn ($r) => (float) $r->bandwidth_util_pct)->take(10)
                ->map(fn ($r) => $fmt($r, ['util' => round((float) $r->bandwidth_util_pct, 1), 'velocidad' => $r->velocidad_mbps]))
                ->values()->all(),
            'errores' => $rows->filter(fn ($r) => $r->err_total > 0 || $r->crc_errors > 0)
                ->sortByDesc(fn ($r) => max($r->err_total ?? 0, (int) $r->crc_errors))->take(10)
                ->map(fn ($r) => $fmt($r, ['errores' => $r->err_total, 'crc' => $r->crc_errors !== null ? (int) $r->crc_errors : null, 'descartes' => $r->disc_total]))
                ->values()->all(),
            'descartes' => $rows->filter(fn ($r) => ($r->disc_total ?? 0) > 0)
                ->sortByDesc('disc_total')->take(10)
                ->map(fn ($r) => $fmt($r, ['descartes' => $r->disc_total]))->values()->all(),
            'degradados' => $up->filter(fn ($r) => ($r->duplex === 'Half')
                    || (in_array((int) $r->velocidad_mbps, [10, 100], true) && ($r->en_enlace || $r->port_type === 'sfp_fiber')))
                ->take(15)
                ->map(fn ($r) => $fmt($r, ['velocidad' => $r->velocidad_mbps, 'duplex' => $r->duplex, 'enlace' => $r->en_enlace]))
                ->values()->all(),
            'broadcast' => $rows->filter(fn ($r) => $r->broadcast_pps !== null && $r->broadcast_pps > 0)
                ->sortByDesc(fn ($r) => (float) $r->broadcast_pps)->take(10)
                ->map(fn ($r) => $fmt($r, ['pps' => round((float) $r->broadcast_pps, 1)]))->values()->all(),
            'optica_medidos' => $rows->whereNotNull('optica_rx_dbm')->count(),
            // -40 dBm es el piso del sensor ("sin lectura"): en un puerto arriba no es una medición real
            'optica_baja' => $up->filter(fn ($r) => $r->optica_rx_dbm !== null && $r->optica_rx_dbm > self::RX_PISO_DBM
                    && $r->optica_rx_dbm < self::RX_BAJO_DBM)
                ->sortBy(fn ($r) => (float) $r->optica_rx_dbm)->take(10)
                ->map(fn ($r) => $fmt($r, ['rx' => (float) $r->optica_rx_dbm, 'tx' => $r->optica_tx_dbm !== null ? (float) $r->optica_tx_dbm : null]))
                ->values()->all(),
        ];
    }

    // =====================================================================
    //  HARDWARE: fuentes, ventiladores, temperatura, PoE, STP, reinicios
    // =====================================================================
    public function hardware(): array
    {
        $chasis = DB::table('telemetria_chasis as c')
            ->join('dispositivos as d', 'd.id', '=', 'c.dispositivo_id')
            ->where('d.estado', '!=', 'offline')
            ->get(['d.id', 'd.nombre', 'c.estado_fuentes', 'c.estado_ventiladores', 'c.temperatura_c',
                'c.poe_total_budget_w', 'c.poe_used_w', 'c.stp_cambios_topologia', 'c.stp_seg_desde_cambio']);

        $componentes = ['psu' => ['total' => 0, 'ok' => 0], 'fan' => ['total' => 0, 'ok' => 0]];
        $fallas = [];
        foreach ($chasis as $c) {
            foreach (['estado_fuentes' => 'psu', 'estado_ventiladores' => 'fan'] as $col => $key) {
                foreach (json_decode($c->$col ?? '[]', true) ?: [] as $comp) {
                    $status = strtoupper((string) ($comp['status'] ?? ''));
                    if (in_array($status, ['NOT PRESENT', ''], true)) {
                        continue;
                    }
                    $componentes[$key]['total']++;
                    if (AlarmDiagnosisService::componenteEnFalla($status)) {
                        $fallas[] = ['disp_id' => $c->id, 'dispositivo' => $c->nombre,
                            'tipo' => $key === 'psu' ? 'Fuente' : 'Ventilador',
                            'componente' => $comp[$key] ?? '', 'estado' => $status];
                    } elseif ($status !== 'DESCONOCIDO') {
                        $componentes[$key]['ok']++;
                    }
                }
            }
        }

        $conTemp = $chasis->whereNotNull('temperatura_c');
        $conPoe = $chasis->filter(fn ($c) => $c->poe_total_budget_w > 0 && $c->poe_used_w !== null);
        $poeTotal = $conPoe->sum(fn ($c) => (float) $c->poe_total_budget_w);
        $poeUsado = $conPoe->sum(fn ($c) => (float) $c->poe_used_w);

        $ultimas = $this->ultimasMetricas();
        $reinicios = $ultimas->filter(fn ($m) => $m->uptime !== null && $m->uptime < 86400)
            ->sortBy('uptime')
            ->map(fn ($m) => ['disp_id' => $m->dispositivo_id, 'dispositivo' => $m->nombre,
                'hace' => $this->duracion((int) $m->uptime)])
            ->values();

        return [
            'componentes' => $componentes,
            'fallas' => $fallas,
            'temp_max' => $conTemp->max(fn ($c) => (float) $c->temperatura_c),
            'temp_equipos' => $conTemp->count(),
            'temp_top' => $conTemp->sortByDesc(fn ($c) => (float) $c->temperatura_c)->take(5)
                ->map(fn ($c) => ['disp_id' => $c->id, 'dispositivo' => $c->nombre, 'temp' => round((float) $c->temperatura_c, 1)])
                ->values()->all(),
            'poe' => $conPoe->isEmpty() ? null : [
                'total_w' => round($poeTotal), 'usado_w' => round($poeUsado),
                'pct' => $poeTotal > 0 ? round($poeUsado / $poeTotal * 100, 1) : null,
                'switches' => $conPoe->count(),
                'top' => $conPoe->sortByDesc(fn ($c) => $c->poe_used_w / $c->poe_total_budget_w)->take(5)
                    ->map(fn ($c) => ['disp_id' => $c->id, 'dispositivo' => $c->nombre, 'usado' => (float) $c->poe_used_w,
                        'total' => (float) $c->poe_total_budget_w, 'pct' => round($c->poe_used_w / $c->poe_total_budget_w * 100, 1)])
                    ->values()->all(),
            ],
            'stp_equipos' => $chasis->whereNotNull('stp_cambios_topologia')->count(),
            'stp_recientes' => $chasis->filter(fn ($c) => $c->stp_seg_desde_cambio !== null && $c->stp_seg_desde_cambio < 3600)
                ->sortBy('stp_seg_desde_cambio')
                ->map(fn ($c) => ['disp_id' => $c->id, 'dispositivo' => $c->nombre, 'hace' => $this->duracion((int) $c->stp_seg_desde_cambio),
                    'cambios' => (int) $c->stp_cambios_topologia])
                ->values()->all(),
            'reinicios_24h' => $reinicios->all(),
        ];
    }

    // =====================================================================
    //  TOP EQUIPOS: CPU, RAM, latencia, pérdida, jitter
    // =====================================================================
    public function topEquipos(): array
    {
        $m = $this->ultimasMetricas();
        $top = fn (string $campo, int $n = 5) => $m->filter(fn ($r) => $r->$campo !== null && $r->$campo > 0)
            ->sortByDesc(fn ($r) => (float) $r->$campo)->take($n)
            ->map(fn ($r) => ['disp_id' => $r->dispositivo_id, 'dispositivo' => $r->nombre, 'valor' => round((float) $r->$campo, 1)])
            ->values()->all();

        $jitter = DB::table('metricas_red as m')
            ->join('dispositivos as d', 'd.id', '=', 'm.dispositivo_id')
            ->where('m.fecha_registro', '>=', now()->subHour())
            ->whereNotNull('m.ping_ms')
            ->groupBy('m.dispositivo_id', 'd.nombre')
            ->havingRaw('COUNT(*) >= 10')
            ->orderByDesc('jitter')
            ->limit(5)
            ->get([DB::raw('m.dispositivo_id'), DB::raw('d.nombre'), DB::raw('ROUND(STDDEV_POP(m.ping_ms), 1) AS jitter'),
                DB::raw('ROUND(AVG(m.ping_ms), 1) AS ping')]);

        return [
            'cpu' => $top('cpu_usage'),
            'ram' => $top('memory_usage'),
            'latencia' => $top('ping_ms'),
            'perdida' => $top('packet_loss'),
            'jitter' => $jitter->map(fn ($r) => ['disp_id' => $r->dispositivo_id, 'dispositivo' => $r->nombre,
                'valor' => (float) $r->jitter, 'ping' => (float) $r->ping])->all(),
        ];
    }

    // =====================================================================
    //  DISPONIBILIDAD (SLA) desde metricas_red_hora
    // =====================================================================
    public function disponibilidad(): array
    {
        $global = [];
        foreach (['24h' => now()->subHours(24), '7d' => now()->subDays(7), '30d' => now()->subDays(30)] as $k => $desde) {
            $r = DB::table('metricas_red_hora')->where('hora', '>=', $desde->copy()->startOfHour())
                ->selectRaw('SUM(muestras) m, SUM(muestras_up) u, MIN(hora) primera')->first();
            $global[$k] = $r && $r->m > 0
                ? ['pct' => round($r->u / $r->m * 100, 3), 'muestras' => (int) $r->m, 'desde' => $r->primera]
                : null;
        }

        $porEquipo = fn (Carbon $desde) => DB::table('metricas_red_hora as h')
            ->join('dispositivos as d', 'd.id', '=', 'h.dispositivo_id')
            ->where('h.hora', '>=', $desde->copy()->startOfHour())
            ->groupBy('h.dispositivo_id', 'd.nombre')
            ->get([DB::raw('h.dispositivo_id'), DB::raw('d.nombre'),
                DB::raw('ROUND(SUM(h.muestras_up) / SUM(h.muestras) * 100, 2) AS pct'), DB::raw('SUM(h.muestras) AS muestras')]);

        $dia = $porEquipo(now()->subHours(24));
        $semana = $porEquipo(now()->subDays(7));

        // Disponibilidad global por hora (últimas 24 h) para la mini gráfica de la franja de salud
        $porHora = DB::table('metricas_red_hora')->where('hora', '>=', now()->subHours(23)->startOfHour())
            ->groupBy('hora')->orderBy('hora')
            ->get([DB::raw('hora'), DB::raw('ROUND(SUM(muestras_up) / SUM(muestras) * 100, 2) AS pct')]);

        return [
            'global' => $global,
            'por_hora' => $porHora->map(fn ($r) => ['hora' => Carbon::parse($r->hora)->format('H:i'), 'pct' => (float) $r->pct])->all(),
            'equipos_24h' => $dia->count(),
            'equipos_100' => $dia->where('pct', '>=', 100)->count(),
            'equipos_bajo_99' => $dia->where('pct', '<', 99)->count(),
            'peores_7d' => $semana->where('pct', '<', 100)->sortBy('pct')->take(10)
                ->map(fn ($r) => ['disp_id' => $r->dispositivo_id, 'dispositivo' => $r->nombre, 'pct' => (float) $r->pct,
                    'muestras' => (int) $r->muestras])
                ->values()->all(),
        ];
    }

    // =====================================================================
    //  SERVICIOS WEB
    // =====================================================================
    public function serviciosWeb(): array
    {
        $servicios = DB::table('servicios_web')->where('es_activo', true)->get();
        if ($servicios->isEmpty()) {
            return ['total' => 0];
        }

        $hist = DB::table('historico_servicio_web')
            ->where('created_at', '>=', now()->subHours(24))
            ->groupBy('servicio_web_id')
            ->get([DB::raw('servicio_web_id'), DB::raw("SUM(estado <> 'offline') AS up"), DB::raw('COUNT(*) AS n')])
            ->keyBy('servicio_web_id');
        $totalN = $hist->sum('n');

        $online = $servicios->where('estado', 'online');
        return [
            'total' => $servicios->count(),
            'online' => $online->count(),
            'warning' => $servicios->where('estado', 'warning')->count(),
            'offline' => $servicios->where('estado', 'offline')->count(),
            'latencia_prom' => $online->whereNotNull('tiempo_respuesta_ms')->avg('tiempo_respuesta_ms') !== null
                ? round($online->whereNotNull('tiempo_respuesta_ms')->avg('tiempo_respuesta_ms')) : null,
            'disponibilidad_24h' => $totalN > 0 ? round($hist->sum('up') / $totalN * 100, 2) : null,
            'lentos' => $servicios->whereNotNull('tiempo_respuesta_ms')->sortByDesc('tiempo_respuesta_ms')->take(5)
                ->map(fn ($s) => ['nombre' => $s->nombre, 'ms' => round($s->tiempo_respuesta_ms), 'estado' => $s->estado])
                ->values()->all(),
            'peor_disponibilidad' => $servicios->filter(fn ($s) => isset($hist[$s->id]))
                ->map(fn ($s) => ['nombre' => $s->nombre, 'pct' => round($hist[$s->id]->up / $hist[$s->id]->n * 100, 1)])
                ->where('pct', '<', 100)->sortBy('pct')->take(5)->values()->all(),
            'ssl_medidos' => $servicios->whereNotNull('ssl_dias_expiracion')->count(),
            'ssl_por_vencer' => $servicios->filter(fn ($s) => $s->ssl_dias_expiracion !== null && $s->ssl_dias_expiracion <= 30)
                ->sortBy('ssl_dias_expiracion')
                ->map(fn ($s) => ['nombre' => $s->nombre, 'dias' => (int) $s->ssl_dias_expiracion])->values()->all(),
            'ssl_proximo' => $servicios->whereNotNull('ssl_dias_expiracion')->min('ssl_dias_expiracion'),
        ];
    }

    // =====================================================================
    //  INCIDENTES: alarmas activas, MTTR, MTBF (historial de noc:sync)
    // =====================================================================
    public function incidentes(): array
    {
        $activas = DB::table('alarmas_red')->where('estado', '!=', 'resuelta')
            ->selectRaw('severidad, COUNT(*) n')->groupBy('severidad')->pluck('n', 'severidad');
        $primera = DB::table('alarmas_red')->min('fecha_inicio');
        if ($primera === null) {
            return ['registro_desde' => null, 'criticas' => 0, 'advertencias' => 0];
        }

        $desde7 = now()->subDays(7);
        // La ventana real es la menor entre 7 días y el tiempo que lleva el registro
        $ventanaSeg = (int) max(1, min(7 * 86400, Carbon::parse($primera)->diffInSeconds(now())));

        $resueltas = DB::table('alarmas_red')->where('estado', 'resuelta')->where('fecha_resolucion', '>=', $desde7);
        $mttr = (clone $resueltas)->selectRaw('AVG(TIMESTAMPDIFF(SECOND, fecha_inicio, fecha_resolucion)) s, COUNT(*) n')->first();

        $caidas = DB::table('alarmas_red as a')
            ->join('dispositivos as d', 'd.id', '=', 'a.dispositivo_id')
            ->where('a.clave', 'like', 'off-%')
            ->where('a.fecha_inicio', '>=', $desde7)
            ->groupBy('a.dispositivo_id', 'd.nombre')
            ->orderByDesc('n')
            ->get([DB::raw('a.dispositivo_id'), DB::raw('d.nombre'), DB::raw('COUNT(*) n'),
                DB::raw("SUM(TIMESTAMPDIFF(SECOND, a.fecha_inicio, COALESCE(a.fecha_resolucion, NOW()))) caido_s")]);
        $totalCaidas = $caidas->sum('n');
        $equiposMonitoreados = DB::table('dispositivos')->count();

        return [
            'registro_desde' => $primera,
            'ventana' => $this->duracion((int) $ventanaSeg),
            'criticas' => (int) ($activas['critica'] ?? 0),
            'advertencias' => (int) ($activas['advertencia'] ?? 0),
            'nuevas_24h' => DB::table('alarmas_red')->where('fecha_inicio', '>=', now()->subDay())->count(),
            'nuevas_por_hora' => $this->alarmasPorHora(12),
            'resueltas_7d' => (int) ($mttr->n ?? 0),
            'mttr' => $mttr && $mttr->s !== null ? $this->duracion((int) $mttr->s) : null,
            // MTBF de equipos: tiempo de operación acumulado / número de caídas en la ventana
            'mtbf' => $totalCaidas > 0 ? $this->duracion((int) ($ventanaSeg * $equiposMonitoreados / $totalCaidas)) : null,
            'caidas_7d' => $totalCaidas,
            'top_caidas' => $caidas->take(5)->map(fn ($r) => ['disp_id' => $r->dispositivo_id, 'dispositivo' => $r->nombre,
                'caidas' => (int) $r->n, 'caido' => $this->duracion((int) $r->caido_s)])->all(),
            'recientes' => DB::table('alarmas_red')->where('estado', 'resuelta')->orderByDesc('fecha_resolucion')->limit(6)
                ->get(['titulo', 'severidad', 'fecha_inicio', 'fecha_resolucion'])
                ->map(fn ($a) => ['titulo' => $a->titulo, 'severidad' => $a->severidad,
                    'segundos' => (int) Carbon::parse($a->fecha_inicio)->diffInSeconds(Carbon::parse($a->fecha_resolucion)),
                    'duracion' => $this->duracion((int) Carbon::parse($a->fecha_inicio)->diffInSeconds(Carbon::parse($a->fecha_resolucion))),
                    'resuelta' => Carbon::parse($a->fecha_resolucion)->diffForHumans()])
                ->all(),
        ];
    }

    // =====================================================================
    //  COBERTURA DE MONITOREO Y ESTADO DE LOS WORKERS
    // =====================================================================
    public function cobertura(): array
    {
        $disp = DB::table('dispositivos as d')
            ->leftJoin('telemetria_chasis as c', 'c.dispositivo_id', '=', 'd.id')
            ->get(['d.estado', 'c.uptime_str']);
        $enLinea = $disp->where('estado', '!=', 'offline');
        $sinSnmp = $enLinea->filter(fn ($d) => $d->uptime_str === null || in_array($d->uptime_str, ['Sin respuesta SNMP', 'Inaccesible'], true));

        $edad = fn ($ts) => $ts ? (int) Carbon::parse($ts)->diffInSeconds(now()) : null;
        $worker = fn (string $nombre, $ts, int $limite) => [
            'nombre' => $nombre,
            'edad_s' => $edad($ts),
            'edad' => $ts ? Carbon::parse($ts)->diffForHumans() : 'nunca',
            'ok' => $ts !== null && $edad($ts) <= $limite,
        ];

        return [
            'total' => $disp->count(),
            'en_linea' => $enLinea->count(),
            'snmp' => $enLinea->count() - $sinSnmp->count(),
            'solo_icmp' => $sinSnmp->count(),
            'offline' => $disp->where('estado', 'offline')->count(),
            'workers' => [
                $worker('Sondeo SNMP/ICMP (15 s)', DB::table('global_metrics')->max('recorded_at'), 90),
                $worker('Detalle SNMP (5 min)', DB::table('telemetria_chasis')->max('detalle_medido_en'), 900),
                $worker('Servicios web (15 s)', DB::table('servicios_web')->max('ultimo_chequeo'), 120),
                $worker('Alarmas y consolidado (30 s)', Cache::get('noc_sync_ultimo'), 180),
            ],
        ];
    }

    // =====================================================================
    //  EQUIPOS FINALES (tablas MAC/ARP de la última sincronización L2)
    // =====================================================================
    public function endpoints(): array
    {
        $tablas = DB::table('tablas_dispositivo as t')
            ->join('dispositivos as d', 'd.id', '=', 't.dispositivo_id')
            ->get(['d.id', 'd.nombre', 't.mac_table', 't.arp_table', 't.updated_at']);

        $macs = [];
        $ips = [];
        $porSwitch = [];
        foreach ($tablas as $t) {
            $aprendidas = collect(json_decode($t->mac_table ?? '[]', true) ?: [])
                ->filter(fn ($e) => !empty($e['mac']) && strcasecmp((string) ($e['type'] ?? ''), 'Static') !== 0);
            foreach ($aprendidas as $e) {
                $macs[strtolower($e['mac'])] = true;
            }
            foreach (json_decode($t->arp_table ?? '[]', true) ?: [] as $e) {
                if (!empty($e['ip'])) {
                    $ips[$e['ip']] = true;
                }
            }
            if ($aprendidas->isNotEmpty()) {
                $porSwitch[] = ['disp_id' => $t->id, 'dispositivo' => $t->nombre, 'macs' => $aprendidas->count()];
            }
        }
        usort($porSwitch, fn ($a, $b) => $b['macs'] <=> $a['macs']);

        return [
            'macs_unicas' => count($macs) ?: null,
            'ips_arp' => count($ips) ?: null,
            'switches' => count($porSwitch),
            'top' => array_slice($porSwitch, 0, 5),
            'actualizado' => $tablas->max('updated_at'),
        ];
    }

    // =====================================================================
    //  ENLACES CDP/LLDP: estado medido de sus puertos y utilización
    // =====================================================================
    public function enlaces(): array
    {
        $vigente = now()->subSeconds(self::VIGENCIA_PUERTO_S);
        $enlaces = DB::table('enlaces_red as e')
            ->leftJoin('dispositivos as o', 'o.id', '=', 'e.origen_dispositivo_id')
            ->leftJoin('dispositivos as d', 'd.id', '=', 'e.destino_dispositivo_id')
            ->leftJoin('interfaces_red as io', 'io.id', '=', 'e.origen_interfaz_id')
            ->leftJoin('interfaces_red as id2', 'id2.id', '=', 'e.destino_interfaz_id')
            ->leftJoin('telemetria_interfaces as tor', 'tor.interfaz_id', '=', 'e.origen_interfaz_id')
            ->leftJoin('telemetria_interfaces as tde', 'tde.interfaz_id', '=', 'e.destino_interfaz_id')
            ->get([
                'e.id', 'o.nombre as origen', 'd.nombre as destino', 'io.nombre as puerto_o', 'id2.nombre as puerto_d',
                'io.velocidad_mbps as vel', 'tor.oper_status as oper_o', 'tde.oper_status as oper_d',
                'tor.bandwidth_util_pct as util_o', 'tde.bandwidth_util_pct as util_d',
                'tor.updated_at as upd_o', 'tde.updated_at as upd_d',
            ]);

        $estado = function ($e) use ($vigente) {
            $o = $e->upd_o && Carbon::parse($e->upd_o)->gte($vigente) ? $e->oper_o : null;
            $d = $e->upd_d && Carbon::parse($e->upd_d)->gte($vigente) ? $e->oper_d : null;
            if ($o === 'down' || $d === 'down') {
                return 'down';
            }
            return ($o === 'up' || $d === 'up') ? 'up' : null;
        };
        $enlaces->each(function ($e) use ($estado) {
            $e->estado_medido = $estado($e);
            $utils = array_filter([$e->util_o, $e->util_d], fn ($v) => $v !== null);
            $e->util = $utils ? max(array_map('floatval', $utils)) : null;
        });

        $fmt = fn ($e) => ['origen' => $e->origen ?? '¿?', 'destino' => $e->destino ?? '¿?',
            'puerto' => $e->puerto_o ?? $e->puerto_d, 'util' => $e->util !== null ? round($e->util, 1) : null, 'velocidad' => $e->vel];

        return [
            'total' => $enlaces->count(),
            'up' => $enlaces->where('estado_medido', 'up')->count(),
            'down' => $enlaces->where('estado_medido', 'down')->count(),
            'sin_dato' => $enlaces->whereNull('estado_medido')->count(),
            'caidos' => $enlaces->where('estado_medido', 'down')->take(8)->map($fmt)->values()->all(),
            'mas_cargados' => $enlaces->where('estado_medido', 'up')->whereNotNull('util')->sortByDesc('util')->take(8)
                ->map($fmt)->values()->all(),
        ];
    }

    // =====================================================================
    //  FIREWALLS (sesiones, VPN, HA) Y WI-FI (WLC)
    // =====================================================================
    public function seguridad(): array
    {
        $sesiones = $this->ultimasMetricas()->filter(fn ($m) => $m->conexiones_activas !== null)
            ->sortByDesc('conexiones_activas')
            ->map(fn ($m) => ['disp_id' => $m->dispositivo_id, 'dispositivo' => $m->nombre, 'sesiones' => (int) $m->conexiones_activas])
            ->values();

        $chasis = DB::table('telemetria_chasis as c')
            ->join('dispositivos as d', 'd.id', '=', 'c.dispositivo_id')
            ->where('d.estado', '!=', 'offline')
            ->where(fn ($q) => $q->whereNotNull('c.vpn_tuneles')->orWhereNotNull('c.ha_estado')->orWhereNotNull('c.wifi_aps')
                ->orWhereNotNull('c.forti_sistema')->orWhereNotNull('c.forti_sensores')->orWhereNotNull('c.forti_sdwan')
                ->orWhereNotNull('c.forti_vpn_ssl')->orWhereNotNull('c.forti_seguridad'))
            ->get(['d.id', 'd.nombre', 'c.vpn_tuneles', 'c.ha_estado', 'c.wifi_aps', 'c.wifi_clientes',
                'c.forti_sistema', 'c.forti_sensores', 'c.forti_sdwan', 'c.forti_vpn_ssl', 'c.forti_seguridad']);
        $json = fn ($v) => json_decode($v ?? 'null', true);
        $conEquipo = fn ($c) => ['dispositivo' => $c->nombre, 'disp_id' => $c->id];

        $tuneles = $chasis->flatMap(fn ($c) => collect($json($c->vpn_tuneles) ?: [])->map(fn ($t) => $t + $conEquipo($c)));
        $ha = $chasis->filter(fn ($c) => $c->ha_estado)->map(fn ($c) => $conEquipo($c) + ($json($c->ha_estado) ?: []))->values();
        $wlc = $chasis->filter(fn ($c) => $c->wifi_aps);
        $aps = $wlc->flatMap(fn ($c) => $json($c->wifi_aps) ?: []);

        // SD-WAN: caídos primero, luego por pérdida y latencia
        $sdwan = $chasis->flatMap(fn ($c) => collect($json($c->forti_sdwan) ?: [])->map(fn ($e) => $e + $conEquipo($c)))
            ->sortBy(fn ($e) => [($e['estado'] ?? '') === 'dead' ? 0 : 1, -($e['perdida_pct'] ?? 0), -($e['latencia_ms'] ?? 0)])
            ->values();
        $sensores = $chasis->flatMap(fn ($c) => collect($json($c->forti_sensores) ?: [])->map(fn ($s) => $s + $conEquipo($c)));
        $sistemas = $chasis->filter(fn ($c) => $c->forti_sistema)->map(fn ($c) => $conEquipo($c) + ($json($c->forti_sistema) ?: []))->values();
        $ssl = $chasis->filter(fn ($c) => $c->forti_vpn_ssl)->map(fn ($c) => $conEquipo($c) + ($json($c->forti_vpn_ssl) ?: []))->values();
        $ips = $chasis->filter(fn ($c) => $c->forti_seguridad)->map(fn ($c) => $conEquipo($c) + ($json($c->forti_seguridad) ?: []))->values();
        $sumaCiclo = fn (string $k) => $ips->every(fn ($r) => !isset($r[$k])) ? null : $ips->sum(fn ($r) => $r[$k] ?? 0);

        // Firewalls / WLC que no responden SNMP: explica por qué no hay datos de VPN/HA/Wi-Fi
        $sinSnmp = DB::table('dispositivos as d')
            ->leftJoin('telemetria_chasis as c', 'c.dispositivo_id', '=', 'd.id')
            ->whereIn('d.tipo_detectado', ['firewall', 'wlc'])
            ->where('d.estado', '!=', 'offline')
            ->where(fn ($q) => $q->whereNull('c.uptime_str')->orWhereIn('c.uptime_str', ['Sin respuesta SNMP', 'Inaccesible']))
            ->selectRaw('d.tipo_detectado, COUNT(*) n')->groupBy('d.tipo_detectado')->pluck('n', 'tipo_detectado');

        return [
            'sesiones' => $sesiones->take(5)->all(),
            'sesiones_total' => $sesiones->isEmpty() ? null : $sesiones->sum('sesiones'),
            'vpn_total' => $tuneles->count(),
            'vpn_up' => $tuneles->where('estado', 'up')->count(),
            'vpn_caidos' => $tuneles->where('estado', 'down')->take(8)->values()->all(),
            'ha' => $ha->all(),
            'wifi_clientes' => $wlc->isEmpty() ? null : $wlc->sum('wifi_clientes'),
            'wifi_aps' => $aps->count(),
            'wifi_top' => $aps->sortByDesc('clientes')->take(5)->values()->all(),
            'firewalls_sin_snmp' => (int) ($sinSnmp['firewall'] ?? 0),
            'wlc_sin_snmp' => (int) ($sinSnmp['wlc'] ?? 0),

            // FortiGate
            'sdwan' => $sdwan->take(12)->all(),
            'sdwan_total' => $sdwan->count(),
            'sdwan_caidos' => $sdwan->where('estado', 'dead')->count(),
            'tuneles_trafico' => $tuneles->filter(fn ($t) => ($t['mbps_in'] ?? null) !== null)
                ->sortByDesc(fn ($t) => ($t['mbps_in'] ?? 0) + ($t['mbps_out'] ?? 0))->take(8)->values()->all(),
            'sensores_alarma' => $sensores->where('alarma', true)->values()->all(),
            'sensores_total' => $sensores->count(),
            'temp_max_firewalls' => $sensores->where('tipo', 'temperatura')->whereNotNull('valor')
                ->groupBy('disp_id')->map(fn ($g) => ['dispositivo' => $g->first()['dispositivo'], 'disp_id' => $g->first()['disp_id'],
                    'temp' => $g->max('valor'), 'sensor' => $g->sortByDesc('valor')->first()['nombre']])
                ->sortByDesc('temp')->take(5)->values()->all(),
            'sistemas' => $sistemas->sortByDesc(fn ($s) => $s['disco_pct'] ?? -1)->all(),
            'sesiones_nuevas_seg' => $sistemas->every(fn ($s) => ($s['sesiones_nuevas_seg'] ?? null) === null) ? null : $sistemas->sum('sesiones_nuevas_seg'),
            'vpn_ssl' => $ssl->sortByDesc('usuarios')->all(),
            'vpn_ssl_usuarios' => $ssl->isEmpty() ? null : $ssl->sum('usuarios'),
            'ips' => $ips->sortByDesc(fn ($r) => $r['ips_detectadas_ciclo'] ?? -1)->all(),
            'ips_ciclo' => $sumaCiclo('ips_detectadas_ciclo'),
            'ips_criticas_ciclo' => $sumaCiclo('ips_criticas_ciclo'),
            'av_ciclo' => $sumaCiclo('av_detectados_ciclo'),
        ];
    }

    // =====================================================================
    //  TRÁFICO: percentil 95 de 24 h y comparación contra ayer
    // =====================================================================
    public function tendenciaTrafico(): array
    {
        $muestras = DB::table('global_metrics')->where('recorded_at', '>=', now()->subDay())
            ->get(['traffic_mbps', 'traffic_in_mbps', 'traffic_out_mbps']);

        $p95 = function (Collection $vals) {
            $vals = $vals->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->sort()->values();
            return $vals->isEmpty() ? null : round($vals[(int) ceil(0.95 * $vals->count()) - 1], 2);
        };

        $promedio = fn (Carbon $desde, Carbon $hasta) => DB::table('global_metrics')
            ->whereBetween('recorded_at', [$desde, $hasta])
            ->selectRaw('AVG(traffic_mbps) trafico, AVG(latency_ms) latencia, AVG(packet_loss_pct) perdida, AVG(cpu_avg_pct) cpu, COUNT(*) n')
            ->first();
        $hoy = $promedio(now()->subHour(), now());
        $ayer = $promedio(now()->subDay()->subHour(), now()->subDay());

        $comparacion = [];
        foreach (['trafico', 'latencia', 'perdida', 'cpu'] as $k) {
            $a = $hoy->n ? $hoy->$k : null;
            $b = $ayer->n ? $ayer->$k : null;
            $comparacion[$k] = [
                'actual' => $a !== null ? round((float) $a, 2) : null,
                'ayer' => $b !== null ? round((float) $b, 2) : null,
                'delta_pct' => $a !== null && $b !== null && (float) $b != 0.0 ? round(((float) $a - (float) $b) / (float) $b * 100, 1) : null,
            ];
        }

        return [
            'p95_total' => $p95($muestras->pluck('traffic_mbps')),
            'p95_in' => $p95($muestras->pluck('traffic_in_mbps')),
            'p95_out' => $p95($muestras->pluck('traffic_out_mbps')),
            'pico' => $muestras->max('traffic_mbps') !== null ? round((float) $muestras->max('traffic_mbps'), 2) : null,
            'muestras_24h' => $muestras->count(),
            'vs_ayer' => $comparacion,
        ];
    }

    // =====================================================================
    //  SERIES PARA LA GRÁFICA DE TENDENCIAS
    // =====================================================================
    public function series(string $rango): array
    {
        [$desde, $bucket, $formato] = match ($rango) {
            '24h' => [now()->subDay(), 300, 'H:i'],
            '7d' => [now()->subDays(7), 3600, 'd/m H:i'],
            default => [now()->subHour(), 0, 'H:i:s'],
        };

        $q = DB::table('global_metrics')->where('recorded_at', '>=', $desde);
        $rows = $bucket > 0
            ? $q->groupBy('b')->orderBy('b')->get([
                DB::raw("FLOOR(UNIX_TIMESTAMP(recorded_at) / {$bucket}) AS b"), DB::raw('MIN(recorded_at) AS t'),
                DB::raw('AVG(traffic_in_mbps) AS tin'), DB::raw('AVG(traffic_out_mbps) AS tout'),
                DB::raw('AVG(latency_ms) AS lat'), DB::raw('AVG(packet_loss_pct) AS loss'),
                DB::raw('AVG(cpu_avg_pct) AS cpu'), DB::raw('AVG(ram_avg_pct) AS ram'),
            ])
            : $q->orderBy('recorded_at')->get([
                'recorded_at as t', 'traffic_in_mbps as tin', 'traffic_out_mbps as tout', 'latency_ms as lat',
                'packet_loss_pct as loss', 'cpu_avg_pct as cpu', 'ram_avg_pct as ram',
            ]);

        $r = fn ($v, $p = 2) => $v === null ? null : round((float) $v, $p);
        return [
            'rango' => $rango,
            'labels' => $rows->map(fn ($x) => Carbon::parse($x->t)->format($formato))->all(),
            'trafico_in' => $rows->map(fn ($x) => $r($x->tin))->all(),
            'trafico_out' => $rows->map(fn ($x) => $r($x->tout))->all(),
            'latencia' => $rows->map(fn ($x) => $r($x->lat, 1))->all(),
            'perdida' => $rows->map(fn ($x) => $r($x->loss))->all(),
            'cpu' => $rows->map(fn ($x) => $r($x->cpu, 1))->all(),
            'ram' => $rows->map(fn ($x) => $r($x->ram, 1))->all(),
        ];
    }

    // =====================================================================
    //  Auxiliares
    // =====================================================================

    /** Alarmas nuevas por hora en las últimas $horas horas (incluye horas en cero). */
    private function alarmasPorHora(int $horas): array
    {
        $conteos = DB::table('alarmas_red')
            ->where('fecha_inicio', '>=', now()->subHours($horas - 1)->startOfHour())
            ->groupBy('h')
            ->get([DB::raw("DATE_FORMAT(fecha_inicio, '%Y-%m-%d %H:00') AS h"), DB::raw('COUNT(*) AS n')])
            ->pluck('n', 'h');

        $serie = [];
        for ($i = $horas - 1; $i >= 0; $i--) {
            $h = now()->subHours($i)->startOfHour();
            $serie[] = ['hora' => $h->format('H:i'), 'n' => (int) ($conteos[$h->format('Y-m-d H:00')] ?? 0)];
        }
        return $serie;
    }

    /** Última muestra (≤ 2 min) de cada equipo no caído, con su nombre. */
    private function ultimasMetricas(): Collection
    {
        return Cache::remember('dashboard_ultimas_metricas_v1', 30, fn () => DB::table('metricas_red as m')
            ->joinSub(
                DB::table('metricas_red')->where('fecha_registro', '>=', now()->subMinutes(2))
                    ->groupBy('dispositivo_id')->selectRaw('MAX(id) AS id'),
                'x', 'x.id', '=', 'm.id')
            ->join('dispositivos as d', 'd.id', '=', 'm.dispositivo_id')
            ->where('d.estado', '!=', 'offline')
            ->get(['m.dispositivo_id', 'd.nombre', 'm.cpu_usage', 'm.memory_usage', 'm.ping_ms', 'm.packet_loss',
                'm.uptime', 'm.conexiones_activas']));
    }

    private function duracion(int $seg): string
    {
        $seg = max(0, $seg);
        if ($seg < 60) {
            return "{$seg} s";
        }
        if ($seg < 3600) {
            return intdiv($seg, 60) . ' min';
        }
        if ($seg < 86400) {
            return intdiv($seg, 3600) . ' h ' . intdiv($seg % 3600, 60) . ' min';
        }
        return intdiv($seg, 86400) . ' d ' . intdiv($seg % 86400, 3600) . ' h';
    }
}
