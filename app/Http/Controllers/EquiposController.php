<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use App\Services\DashboardMetricsService;

/**
 * Equipos de red: salud operativa, diagnóstico por pestañas e inventario completo.
 * (Antes vivían en el dashboard; el dashboard quedó como vista general de todos los sectores.)
 */
class EquiposController extends Controller
{
    public function index(DashboardMetricsService $metricas)
    {
        $dispositivos = Dispositivo::with(['telemetriaChasis', 'ultimaMetrica'])
            ->orderByRaw("CASE estado WHEN 'online' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderBy('nombre')
            ->get();

        foreach ($dispositivos as $device) {
            // CPU/RAM de la última muestra del poller (dispositivos.cpu_usage no lo actualiza ningún worker).
            // Un equipo caído no tiene lectura vigente.
            $latest = $device->ultimaMetrica;
            $vigente = $device->estado !== 'offline' ? $latest : null;
            $device->latest_cpu = $vigente?->cpu_usage;
            $device->latest_mem = $vigente?->memory_usage;
            $device->latest_loss = $latest?->packet_loss;
            $device->latest_ping = $latest?->ping_ms;

            $typeInfo = $device->resolveTypeInfo();
            $device->tipo_dispositivo = $typeInfo['tipo'];
            $device->tipo_label = $typeInfo['label'];
            $device->clean_model = $typeInfo['clean_model'];
            $device->tipo_badge_classes = $typeInfo['badge_classes'];
            $device->tipo_icon_container_classes = $typeInfo['icon_container'];

            $uptimeStr = $device->telemetriaChasis->uptime_str ?? null;
            if ((!$uptimeStr || str_contains($uptimeStr, 'Inaccesible') || str_contains($uptimeStr, 'Desconectado')) && $latest && $latest->uptime > 0 && $device->estado === 'online') {
                $uptimeStr = floor($latest->uptime / 86400) . 'd ' . floor(($latest->uptime % 86400) / 3600) . 'h';
            }
            $device->clean_uptime = $device->estado === 'online' ? ($uptimeStr ?: 'En línea') : 'Offline';
        }

        return view('equipos.index', [
            'paneles' => $metricas->paneles(),
            'dispositivos' => $dispositivos,
            'totalDispositivos' => $dispositivos->count(),
            'dispositivosOnline' => $dispositivos->where('estado', 'online')->count(),
            'dispositivosOffline' => $dispositivos->where('estado', 'offline')->count(),
            'totalSwitches' => $dispositivos->where('tipo_dispositivo', 'switch')->count(),
            'totalTelefonos' => $dispositivos->where('tipo_dispositivo', 'telefono')->count(),
            'totalServidores' => $dispositivos->where('tipo_dispositivo', 'servidor')->count(),
            'totalPCs' => $dispositivos->where('tipo_dispositivo', 'pc')->count(),
            'totalRouters' => $dispositivos->where('tipo_dispositivo', 'router')->count(),
            'totalAPs' => $dispositivos->where('tipo_dispositivo', 'access_point')->count(),
        ]);
    }
}
