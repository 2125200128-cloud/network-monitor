<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use App\Models\MetricaRed;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\TelemetriaInterfaz;
use App\Models\ConfiguracionDispositivo;
use App\Models\NotificacionLeida;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(\App\Services\DashboardMetricsService $metricas)
    {
        $dispositivos = Dispositivo::with(['telemetriaChasis', 'ultimaMetrica'])
            ->orderByRaw("CASE estado WHEN 'online' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderBy('nombre')
            ->get();
        
        $totalDispositivos = $dispositivos->count();
        $dispositivosOnline = $dispositivos->where('estado', 'online')->count();
        $dispositivosOffline = $dispositivos->where('estado', 'offline')->count();
        $dispositivosWarning = $dispositivos->where('estado', 'warning')->count();

        // -----------------------------------------------------------------------
        // Indicadores globales: solo valores medidos. null = sin dato disponible.
        // -----------------------------------------------------------------------
        $onlineMetrics = $dispositivos->where('estado', '!=', 'offline')
            ->map(fn($d) => $d->ultimaMetrica)->filter()->values();

        $avgOrNull = fn($collection, $key, $precision = 1) =>
            ($v = $collection->whereNotNull($key)->avg($key)) === null ? null : round($v, $precision);

        $avgCpu  = $avgOrNull($onlineMetrics, 'cpu_usage');
        $avgMem  = $avgOrNull($onlineMetrics, 'memory_usage');
        $avgTemp = $avgOrNull($onlineMetrics, 'temperatura_celsius');
        $erroresUltimoCiclo = $onlineMetrics->whereNotNull('errores_interfaz')->isEmpty()
            ? null : (int) $onlineMetrics->sum('errores_interfaz');

        // Uptime mínimo entre dispositivos en línea (eslabón más débil)
        $globalUptime = $onlineMetrics->where('uptime', '>', 0)->min('uptime');
        $formattedUptime = $globalUptime === null
            ? 'Sin dato'
            : floor($globalUptime / 86400) . 'd ' . floor(($globalUptime % 86400) / 3600) . 'h';

        $globalRow = DB::table('global_metrics')->latest('recorded_at')->first();
        $avgPing       = $globalRow?->latency_ms !== null ? round($globalRow->latency_ms, 1) : $avgOrNull($onlineMetrics, 'ping_ms');
        $avgPacketLoss = $globalRow?->packet_loss_pct !== null ? round($globalRow->packet_loss_pct, 2) : null;
        $trafficTotal  = $globalRow?->traffic_mbps !== null ? round($globalRow->traffic_mbps, 2) : null;
        $trafficInNow  = $globalRow?->traffic_in_mbps ?? null;
        $trafficOutNow = $globalRow?->traffic_out_mbps ?? null;
        $avgPortSat    = $globalRow?->port_saturation_pct !== null ? round($globalRow->port_saturation_pct, 1) : null;
        if ($globalRow?->cpu_avg_pct !== null) $avgCpu = round($globalRow->cpu_avg_pct, 1);
        if ($globalRow?->ram_avg_pct !== null) $avgMem = round($globalRow->ram_avg_pct, 1);

        // Series para las gráficas (últimas 24 muestras reales de global_metrics)
        $globalSeries = DB::table('global_metrics')
            ->orderByDesc('recorded_at')
            ->limit(24)
            ->get()
            ->reverse()
            ->values();

        $labels      = $globalSeries->map(fn($r) => Carbon::parse($r->recorded_at)->format('H:i:s'))->toArray();
        $pingData    = $globalSeries->map(fn($r) => $r->latency_ms !== null ? round($r->latency_ms, 1) : null)->toArray();
        $trafficData = $globalSeries->map(fn($r) => $r->traffic_mbps !== null ? round($r->traffic_mbps, 2) : null)->toArray();
        $lossData    = $globalSeries->map(fn($r) => $r->packet_loss_pct !== null ? round($r->packet_loss_pct, 2) : null)->toArray();

        // Attach latest metrics, type info and platform info to each device for the inventory table
        foreach ($dispositivos as $device) {
            // CPU/RAM de la última muestra del poller (dispositivos.cpu_usage no lo actualiza ningún worker).
            // Un equipo caído no tiene lectura vigente.
            $latest = $device->ultimaMetrica;
            $vigente = $device->estado !== 'offline' ? $latest : null;
            $device->latest_cpu = $vigente?->cpu_usage;
            $device->latest_mem = $vigente?->memory_usage;
            $device->latest_loss = $latest?->packet_loss;
            $device->latest_ping = $latest?->ping_ms;

            // Resolve Type, Icon & Clean Model Info
            $typeInfo = $device->resolveTypeInfo();
            $device->tipo_dispositivo = $typeInfo['tipo'];
            $device->tipo_label = $typeInfo['label'];
            $device->clean_model = $typeInfo['clean_model'];
            $device->tipo_badge_classes = $typeInfo['badge_classes'];
            $device->tipo_icon_container_classes = $typeInfo['icon_container'];

            // Clean Uptime
            $uptimeStr = $device->telemetriaChasis->uptime_str ?? null;
            if ((!$uptimeStr || str_contains($uptimeStr, 'Inaccesible') || str_contains($uptimeStr, 'Desconectado')) && $latest && $latest->uptime > 0 && $device->estado === 'online') {
                $days = floor($latest->uptime / 86400);
                $hrs = floor(($latest->uptime % 86400) / 3600);
                $uptimeStr = "{$days}d {$hrs}h";
            }
            $device->clean_uptime = ($device->estado === 'online') 
                ? ($uptimeStr ?: 'En línea') 
                : 'Offline';
        }

        // Conteos por categoría de dispositivo
        $totalSwitches = $dispositivos->where('tipo_dispositivo', 'switch')->count();
        $totalTelefonos = $dispositivos->where('tipo_dispositivo', 'telefono')->count();
        $totalServidores = $dispositivos->where('tipo_dispositivo', 'servidor')->count();
        $totalPCs = $dispositivos->where('tipo_dispositivo', 'pc')->count();
        $totalRouters = $dispositivos->where('tipo_dispositivo', 'router')->count();
        $totalAPs = $dispositivos->where('tipo_dispositivo', 'access_point')->count();

        // Compilar notificaciones inteligentes de red
        $notificaciones = $this->compilarNotificaciones($dispositivos);
        $unreadNotificaciones = count(array_filter($notificaciones, fn($n) => !$n['leida']));

        $paneles = $metricas->paneles();

        return view('dashboard', compact(
            'paneles',
            'dispositivos', 
            'totalDispositivos', 
            'dispositivosOnline', 
            'dispositivosOffline', 
            'dispositivosWarning', 
            'totalSwitches',
            'totalTelefonos',
            'totalServidores',
            'totalPCs',
            'totalRouters',
            'totalAPs',
            'avgPing',
            'avgPacketLoss',
            'avgCpu',
            'avgMem',
            'avgTemp',
            'trafficTotal',
            'trafficInNow',
            'trafficOutNow',
            'erroresUltimoCiclo',
            'avgPortSat',
            'formattedUptime',
            'labels',
            'pingData',
            'trafficData',
            'lossData',
            'notificaciones',
            'unreadNotificaciones'
        ));
    }

    /**
     * Compila todas las alertas en tiempo real con análisis de CAUSA RAÍZ (desconexiones, cascada, puertos, térmicas, cpu).
     * El diagnóstico se comparte unos segundos entre todos; el estado leída/descartada se aplica por usuario.
     */
    public function compilarNotificaciones($dispositivos = null): array
    {
        $alarmas = \App\Services\AlarmDiagnosisService::diagnosticoCompartido();

        return (new \App\Services\AlarmDiagnosisService())->aplicarEstadoUsuario($alarmas);
    }

    /**
     * Endpoint JSON para refrescar KPIs del dashboard en tiempo real (fetch cada 30s).
     * Retorna los últimos valores de global_metrics + contadores de dispositivos + alertas en vivo.
     */
    public function kpiLive(): JsonResponse
    {
        $data = \Illuminate\Support\Facades\Cache::remember('kpi_live_fast', 5, function () {
            $g = DB::table('global_metrics')->latest('recorded_at')->first();

            $counts = DB::table('dispositivos')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'online' THEN 1 ELSE 0 END) as online,
                    SUM(CASE WHEN estado = 'offline' THEN 1 ELSE 0 END) as offline,
                    SUM(CASE WHEN estado = 'warning' THEN 1 ELSE 0 END) as warning
                ")->first();

            $online  = (int) ($counts->online ?? 0);
            $offline = (int) ($counts->offline ?? 0);
            $warning = (int) ($counts->warning ?? 0);
            $total   = (int) ($counts->total ?? 0);

            $r = fn($v, $p = 1) => $v === null ? null : round((float) $v, $p);

            return [
                'traffic_mbps'       => $r($g?->traffic_mbps, 2),
                'traffic_in_mbps'    => $r($g?->traffic_in_mbps ?? null, 2),
                'traffic_out_mbps'   => $r($g?->traffic_out_mbps ?? null, 2),
                'latency_ms'         => $r($g?->latency_ms),
                'packet_loss_pct'    => $r($g?->packet_loss_pct, 2),
                'cpu_avg_pct'        => $r($g?->cpu_avg_pct),
                'ram_avg_pct'        => $r($g?->ram_avg_pct),
                'port_saturation_pct'=> $r($g?->port_saturation_pct),
                'active_nodes'       => $g ? $g->active_nodes : $online,
                'snmp_online'        => $online,
                'dispositivos'       => compact('online', 'offline', 'warning', 'total'),
                'recorded_at'        => $g?->recorded_at,
            ];
        });

        $notificaciones = $this->compilarNotificaciones();
        $data['notificaciones'] = $notificaciones;
        $data['unread_notif_count'] = count(array_filter($notificaciones, fn($n) => !$n['leida']));

        return response()->json($data);
    }

    /**
     * Series de global_metrics para la gráfica de tendencias (1h crudo, 24h en bloques de 5 min, 7d por hora).
     */
    public function series(Request $request, \App\Services\DashboardMetricsService $metricas): JsonResponse
    {
        $rango = in_array($request->query('rango'), ['1h', '24h', '7d'], true) ? $request->query('rango') : '1h';

        return response()->json(
            \Illuminate\Support\Facades\Cache::remember("dashboard_series_{$rango}", $rango === '1h' ? 15 : 120,
                fn () => $metricas->series($rango))
        );
    }

    /**
     * Marca una o todas las notificaciones como leídas para la cuenta autenticada.
     */
    public function marcarNotificacionLeida(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $all = $request->boolean('all');
        $id = $request->input('id');

        if ($all) {
            $alarmas = $this->compilarNotificaciones();
            foreach ($alarmas as $alarma) {
                NotificacionLeida::updateOrCreate(
                    ['user_id' => $user->id, 'notificacion_id' => $alarma['id']],
                    ['estado' => 'leida', 'fecha_evento' => now()]
                );
            }
        } elseif ($id) {
            NotificacionLeida::updateOrCreate(
                ['user_id' => $user->id, 'notificacion_id' => $id],
                ['estado' => 'leida', 'fecha_evento' => now()]
            );
        }

        $notificaciones = $this->compilarNotificaciones();
        $unreadCount = count(array_filter($notificaciones, fn($n) => !$n['leida']));

        return response()->json([
            'success' => true,
            'message' => 'Notificaciones marcadas como leídas',
            'unread_notif_count' => $unreadCount
        ]);
    }

    /**
     * Descarta/elimina una o todas las notificaciones de la vista para la cuenta autenticada.
     */
    public function descartarNotificacion(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $all = $request->boolean('all');
        $id = $request->input('id');

        if ($all) {
            $alarmas = $this->compilarNotificaciones();
            foreach ($alarmas as $alarma) {
                NotificacionLeida::updateOrCreate(
                    ['user_id' => $user->id, 'notificacion_id' => $alarma['id']],
                    ['estado' => 'descartada', 'fecha_evento' => now()]
                );
            }
        } elseif ($id) {
            NotificacionLeida::updateOrCreate(
                ['user_id' => $user->id, 'notificacion_id' => $id],
                ['estado' => 'descartada', 'fecha_evento' => now()]
            );
        }

        $notificaciones = $this->compilarNotificaciones();
        $unreadCount = count(array_filter($notificaciones, fn($n) => !$n['leida']));

        return response()->json([
            'success' => true,
            'message' => 'Notificación descartada correctamente',
            'unread_notif_count' => $unreadCount,
            'remaining_count' => count($notificaciones)
        ]);
    }

    public function descargarInventarioPdf(\App\Services\PdfReportService $pdfService)
    {
        return $pdfService->descargarReporteInventario();
    }

    /**
     * Tráfico en vivo medido por snmp_poller.py (delta de contadores ifHC*Octets de puertos físicos).
     * Fuente: el switch core configurado en Ajustes; si no hay core o aún no tiene muestra, la suma
     * de todos los equipos. Si no hay mediciones recientes responde status=no_data.
     */
    public function snmpLiveTraffic(): JsonResponse
    {
        $data = \Illuminate\Support\Facades\Cache::remember('snmp_live_traffic_fast', 2, function () {
            $limite = now()->subMinutes(2);
            $coreIp = \App\Models\ConfiguracionGeneral::query()->value('ip_switch_core');

            if ($coreIp && ($core = Dispositivo::where('ip', $coreIp)->first())) {
                $m = MetricaRed::where('dispositivo_id', $core->id)
                    ->where('fecha_registro', '>=', $limite)
                    ->whereNotNull('traffic_in_mbps')
                    ->orderByDesc('fecha_registro')
                    ->first();
                if ($m) {
                    return [
                        'status'      => 'success',
                        'fuente'      => 'core',
                        'in_mbps'     => (float) $m->traffic_in_mbps,
                        'out_mbps'    => (float) $m->traffic_out_mbps,
                        'dispositivo' => $core->nombre,
                        'ip'          => $core->ip,
                        'medido_en'   => (string) $m->fecha_registro,
                    ];
                }
            }

            $g = DB::table('global_metrics')
                ->where('recorded_at', '>=', $limite)
                ->whereNotNull('traffic_in_mbps')
                ->latest('recorded_at')
                ->first();
            if ($g) {
                return [
                    'status'      => 'success',
                    'fuente'      => 'global',
                    'in_mbps'     => (float) $g->traffic_in_mbps,
                    'out_mbps'    => (float) $g->traffic_out_mbps,
                    'dispositivo' => 'Suma de puertos físicos de todos los equipos',
                    'ip'          => null,
                    'medido_en'   => (string) $g->recorded_at,
                ];
            }

            return [
                'status'  => 'no_data',
                'message' => 'Sin mediciones de tráfico en los últimos 2 minutos (¿está corriendo snmp_poller.py?)',
            ];
        });

        return response()->json($data);
    }

    /**
     * Ejecuta test_cdp.py y retorna las adyacencias CDP descubiertas.
     */
    public function cdpLive(): \Illuminate\Http\JsonResponse
    {
        $cachedCdp = \Illuminate\Support\Facades\Cache::remember('cdp_live_data', 30, function () {
            $scriptPath = base_path('test_cdp.py');

            if (!file_exists($scriptPath)) {
                return [
                    'status'   => 'error',
                    'message'  => 'Script test_cdp.py no encontrado.',
                    'adyacencias' => []
                ];
            }

            $pythonBin = escapeshellarg(\App\Support\Python::binario());
            $output = shell_exec("{$pythonBin} " . escapeshellarg($scriptPath) . ' 2>&1');

            if (empty($output)) {
                return [
                    'status'   => 'error',
                    'message'  => 'El script CDP no produjo ninguna salida.',
                    'adyacencias' => []
                ];
            }

            // Extraer el JSON del output
            $json = null;
            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                if (str_starts_with($line, '{')) {
                    $decoded = json_decode($line, true);
                    if ($decoded !== null) {
                        $json = $decoded;
                        break;
                    }
                }
            }

            if ($json === null) {
                return [
                    'status'   => 'error',
                    'message'  => 'No se pudo parsear la salida del script CDP.',
                    'raw'      => $output,
                    'adyacencias' => []
                ];
            }

            return $json;
        });

        return response()->json($cachedCdp, (isset($cachedCdp['status']) && $cachedCdp['status'] === 'error' && isset($cachedCdp['raw']) === false) ? 404 : 200);
    }

    private function cleanDeviceModel(?string $rawModel): string
    {
        if (!$rawModel) return 'Cisco Catalyst / IOS';
        
        // Check specific platforms first
        if (stripos($rawModel, 'n7000') !== false || stripos($rawModel, 'Nexus 7') !== false) return 'Cisco Nexus 7000';
        if (stripos($rawModel, 'n3000') !== false || stripos($rawModel, 'Nexus 3') !== false) return 'Cisco Nexus 3000';
        if (stripos($rawModel, 'nxos') !== false || stripos($rawModel, 'N9K') !== false || stripos($rawModel, 'Nexus 9') !== false) return 'Cisco Nexus 9000';
        if (stripos($rawModel, 'C9606') !== false) return 'Cisco Catalyst 9606R';
        if (stripos($rawModel, 'C9800') !== false) return 'Cisco Catalyst 9800-L WLC';
        if (stripos($rawModel, 'C9200') !== false) return 'Cisco Catalyst 9200L';
        if (stripos($rawModel, 'C9300') !== false) return 'Cisco Catalyst 9300';
        if (stripos($rawModel, 'C1000') !== false) return 'Cisco Catalyst 1000';
        if (stripos($rawModel, 'CAT9K') !== false) return 'Cisco Catalyst 9000-L';
        if (stripos($rawModel, '2960') !== false) return 'Cisco Catalyst 2960X';
        if (stripos($rawModel, '3750') !== false) return 'Cisco Catalyst 3750';
        if (stripos($rawModel, '1841') !== false) return 'Cisco Router 1841';
        if (stripos($rawModel, 'R5000') !== false || stripos($rawModel, 'WANFleX') !== false) return 'InfiNet R5000 MINT';
        if (stripos($rawModel, 'SG200') !== false) return 'Cisco SG200-26';
        
        return 'Cisco Switch L2/L3';
    }
}
