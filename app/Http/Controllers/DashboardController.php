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
    public function index()
    {
        $dispositivos = Dispositivo::with('telemetriaChasis')
            ->orderByRaw("FIELD(estado, 'online', 'warning', 'offline')")
            ->orderBy('nombre')
            ->get();
        
        $totalDispositivos = $dispositivos->count();
        $dispositivosOnline = $dispositivos->where('estado', 'online')->count();
        $dispositivosOffline = $dispositivos->where('estado', 'offline')->count();
        $dispositivosWarning = $dispositivos->where('estado', 'warning')->count();

        $dispositivoIds = $dispositivos->pluck('id');

        // -----------------------------------------------------------------------
        // Global Averages — FUENTE PRIMARIA: global_metrics (collector)
        // Fallback: promedios de metricas_red por dispositivo
        // -----------------------------------------------------------------------
        $latestMetricsForAvg = collect();
        foreach ($dispositivos as $disp) {
            $latest = $disp->metricas()->orderByDesc('fecha_registro')->first();
            if ($latest) {
                $latestMetricsForAvg->push($latest);
            }
        }

        // Promedios de per-device (CPU, RAM, Temp, Errores)
        $avgCpu  = round($latestMetricsForAvg->avg('cpu_usage') ?? 0, 1);
        $avgMem  = round($latestMetricsForAvg->avg('memory_usage') ?? 0, 1);
        $avgTemp = round($latestMetricsForAvg->avg('temperatura_celsius') ?? 0, 1);
        $avgErrores = round($latestMetricsForAvg->avg('errores_interfaz') ?? 0, 1);

        // Uptime mínimo entre dispositivos online (eslabón más débil)
        $globalUptime = $latestMetricsForAvg->where('uptime', '>', 0)->min('uptime') ?? 0;
        $uptimeDays   = floor($globalUptime / 86400);
        $uptimeHours  = floor(($globalUptime % 86400) / 3600);
        $formattedUptime = "{$uptimeDays}d {$uptimeHours}h";

        // KPIs de red desde global_metrics (fuente real: metrics_collector.py)
        $globalRow = DB::table('global_metrics')->latest('recorded_at')->first();
        if ($globalRow) {
            $avgPing        = round($globalRow->latency_ms, 1);
            $avgPacketLoss  = round($globalRow->packet_loss_pct, 2);
            $avgConexiones  = round($globalRow->traffic_mbps, 2);   // Tráfico total Mbps
            $avgPortSat     = round($globalRow->port_saturation_pct, 1);
            // Si global_metrics tiene CPU/RAM válidos, usa los globales
            if ($globalRow->cpu_avg_pct > 0) $avgCpu = round($globalRow->cpu_avg_pct, 1);
            if ($globalRow->ram_avg_pct > 0) $avgMem = round($globalRow->ram_avg_pct, 1);
        } else {
            // Fallback a metricas_red
            $avgPing       = round($latestMetricsForAvg->where('ping_ms', '>', 0)->avg('ping_ms') ?? 0, 1);
            $avgPacketLoss = round($latestMetricsForAvg->avg('packet_loss') ?? 0, 2);
            $avgConexiones = round($latestMetricsForAvg->avg('conexiones_activas') ?? 0, 2);
            $avgPortSat    = 0;
        }

        // Datos para chartz sparklines (últimas 24 muestras de global_metrics)
        $globalSeries = DB::table('global_metrics')
            ->orderByDesc('recorded_at')
            ->limit(24)
            ->get()
            ->reverse()
            ->values();

        $labels     = $globalSeries->map(fn($r) => Carbon::parse($r->recorded_at)->format('H:i'))->toArray();
        $pingData   = $globalSeries->map(fn($r) => round($r->latency_ms, 1))->toArray();
        $trafficIn  = $globalSeries->map(fn($r) => round($r->traffic_mbps, 2))->toArray();
        $trafficOut = $globalSeries->map(fn($r) => round($r->packet_loss_pct, 2))->toArray();

        // Si global_metrics está vacía, fallback a metricas_red históricas
        if (empty($labels)) {
            $latestMetrics  = MetricaRed::orderBy('fecha_registro', 'desc')
                ->take(24 * max(1, $totalDispositivos))->get()->reverse();
            $groupedMetrics = $latestMetrics->groupBy('fecha_registro');
            foreach ($groupedMetrics as $time => $metricsAtTime) {
                $labels[]     = Carbon::parse($time)->format('H:i');
                $pingData[]   = round($metricsAtTime->avg('ping_ms') ?? 0, 2);
                $mbpsIn       = ($metricsAtTime->sum('bytes_in') * 8) / 1000000 / 300;
                $trafficIn[]  = round($mbpsIn, 2);
                $mbpsOut      = ($metricsAtTime->sum('bytes_out') * 8) / 1000000 / 300;
                $trafficOut[] = round($mbpsOut, 2);
            }
        }

        // Attach latest metrics, type info and platform info to each device for the inventory table
        foreach ($dispositivos as $device) {
            $latest = $latestMetricsForAvg->firstWhere('dispositivo_id', $device->id)
                ?? $device->metricas()->orderByDesc('fecha_registro')->first();
            $device->latest_cpu = $latest ? $latest->cpu_usage : 0;
            $device->latest_loss = $latest ? $latest->packet_loss : 0;
            $device->latest_ping = $latest ? $latest->ping_ms : 0;

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

        return view('dashboard', compact(
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
            'avgConexiones',
            'avgErrores',
            'avgPortSat',
            'formattedUptime',
            'labels',
            'pingData',
            'trafficIn',
            'trafficOut',
            'notificaciones',
            'unreadNotificaciones'
        ));
    }

    /**
     * Compila todas las alertas en tiempo real con análisis de CAUSA RAÍZ (desconexiones, cascada, puertos, térmicas, cpu).
     */
    public function compilarNotificaciones($dispositivos = null): array
    {
        $diagnosisService = new \App\Services\AlarmDiagnosisService();
        return $diagnosisService->diagnosticarAlarmasRed();
    }

    /**
     * Endpoint JSON para refrescar KPIs del dashboard en tiempo real (fetch cada 30s).
     * Retorna los últimos valores de global_metrics + contadores de dispositivos + alertas en vivo.
     */
    public function kpiLive(): JsonResponse
    {
        $g = DB::table('global_metrics')->latest('recorded_at')->first();

        $online  = DB::table('dispositivos')->where('estado', 'online')->count();
        $offline = DB::table('dispositivos')->where('estado', 'offline')->count();
        $warning = DB::table('dispositivos')->where('estado', 'warning')->count();
        $total   = $online + $offline + $warning;

        // Per-device CPU/RAM/Ping desde metricas_red
        $perDevice = DB::table('metricas_red as m')
            ->joinSub(
                DB::table('metricas_red')
                    ->select('dispositivo_id', DB::raw('MAX(fecha_registro) as last_ts'))
                    ->groupBy('dispositivo_id'),
                'latest', fn($j) => $j->on('m.dispositivo_id', '=', 'latest.dispositivo_id')
                                       ->on('m.fecha_registro', '=', 'latest.last_ts')
            )
            ->select(
                DB::raw('AVG(m.cpu_usage) as cpu, AVG(m.memory_usage) as ram, AVG(m.ping_ms) as ping, MIN(m.uptime) as uptime'),
                DB::raw('SUM(CASE WHEN (m.cpu_usage > 0 OR m.memory_usage > 0) AND m.fecha_registro >= NOW() - INTERVAL 5 MINUTE THEN 1 ELSE 0 END) as snmp_online_count')
            )
            ->first();

        $notificaciones = $this->compilarNotificaciones();
        $unreadCount = count(array_filter($notificaciones, fn($n) => !$n['leida']));

        return response()->json([
            'traffic_mbps'       => $g ? round($g->traffic_mbps, 2)       : 0,
            'latency_ms'         => $g ? round($g->latency_ms, 1)          : round($perDevice->ping ?? 0, 1),
            'packet_loss_pct'    => $g ? round($g->packet_loss_pct, 2)     : 0,
            'cpu_avg_pct'        => $g && $g->cpu_avg_pct > 0
                                        ? round($g->cpu_avg_pct, 1)
                                        : round($perDevice->cpu ?? 0, 1),
            'ram_avg_pct'        => $g && $g->ram_avg_pct > 0
                                        ? round($g->ram_avg_pct, 1)
                                        : round($perDevice->ram ?? 0, 1),
            'port_saturation_pct'=> $g ? round($g->port_saturation_pct, 1) : 0,
            'active_nodes'       => $g ? $g->active_nodes : $online,
            'snmp_online'        => (int) ($perDevice->snmp_online_count ?? 0),
            'dispositivos'       => compact('online', 'offline', 'warning', 'total'),
            'uptime_min'         => $perDevice->uptime ?? 0,
            'recorded_at'        => $g ? $g->recorded_at : now()->toIso8601String(),
            'notificaciones'     => $notificaciones,
            'unread_notif_count' => $unreadCount,
        ]);
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
            $diagnosisService = new \App\Services\AlarmDiagnosisService();
            $alarmas = $diagnosisService->diagnosticarAlarmasRed();
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
            $diagnosisService = new \App\Services\AlarmDiagnosisService();
            $alarmas = $diagnosisService->diagnosticarAlarmasRed();
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
     * Ejecuta worker/snmp_traffic.py y retorna la telemetría de tráfico en tiempo real como JSON.
     * Llamado por el frontend vía fetch() cada 3 segundos.
     */
    public function snmpLiveTraffic(): \Illuminate\Http\JsonResponse
    {
        $scriptPath = base_path('worker/snmp_traffic.py');

        if (!file_exists($scriptPath)) {
            return response()->json([
                'status'   => 'error',
                'message'  => 'Script worker/snmp_traffic.py no encontrado.',
                'in_mbps'  => 0,
                'out_mbps' => 0,
            ], 404);
        }

        $pythonBin = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
        $output = shell_exec("{$pythonBin} " . escapeshellarg($scriptPath) . ' 2>&1');

        if (empty($output)) {
            return response()->json([
                'status'   => 'error',
                'message'  => 'El script no produjo ninguna salida.',
                'in_mbps'  => 0,
                'out_mbps' => 0,
            ]);
        }

        // Extraer el JSON del output (puede contener stderr antes del JSON)
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
            return response()->json([
                'status'   => 'error',
                'message'  => 'No se pudo parsear la salida del script.',
                'raw'      => $output,
                'in_mbps'  => 0,
                'out_mbps' => 0,
            ]);
        }

        return response()->json($json);
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

            $pythonBin = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
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
