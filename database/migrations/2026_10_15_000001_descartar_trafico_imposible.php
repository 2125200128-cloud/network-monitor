<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Antes de que snmp_poller.py validara las tasas contra ifHighSpeed, una discontinuidad de contadores
 * (reinicio de interfaz, ifIndex reasignado) producía tasas imposibles (p.ej. 3 Tbps en un switch de acceso)
 * que se sumaban al tráfico global. Se descartan las muestras cuyo tráfico supera la suma de velocidades
 * de los puertos físicos del equipo, y el total global del mismo ciclo de sondeo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $capacidad = DB::table('interfaces_red')
            ->whereIn('port_type', ['copper_rj45', 'sfp_fiber'])
            ->where('velocidad_mbps', '>', 0)
            ->groupBy('dispositivo_id')
            ->selectRaw('dispositivo_id, SUM(velocidad_mbps) * 1.1 AS cap');

        $invalidas = DB::table('metricas_red as m')
            ->joinSub($capacidad, 'c', 'c.dispositivo_id', '=', 'm.dispositivo_id')
            ->where(fn ($q) => $q->whereColumn('m.traffic_in_mbps', '>', 'c.cap')->orWhereColumn('m.traffic_out_mbps', '>', 'c.cap'))
            ->get(['m.id', 'm.fecha_registro']);

        foreach ($invalidas->chunk(500) as $chunk) {
            DB::table('metricas_red')->whereIn('id', $chunk->pluck('id'))
                ->update(['traffic_in_mbps' => null, 'traffic_out_mbps' => null]);
        }

        // El global se escribe al cerrar el ciclo, segundos después de las métricas por equipo
        foreach ($invalidas->pluck('fecha_registro')->unique() as $fecha) {
            DB::table('global_metrics')
                ->whereBetween('recorded_at', [$fecha, Carbon::parse($fecha)->addSeconds(60)])
                ->orderBy('recorded_at')
                ->limit(1)
                ->update(['traffic_mbps' => null, 'traffic_in_mbps' => null, 'traffic_out_mbps' => null]);
        }
    }

    public function down(): void
    {
        // Las tasas descartadas no eran mediciones válidas; no se restauran.
    }
};
