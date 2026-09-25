<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DispositivoController;
use App\Models\MetricaRed;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Rutas para la gestión de dispositivos
    Route::apiResource('dispositivos', DispositivoController::class);

    // Endpoint para obtener métricas para Grafana o Dashboard
    Route::get('/v1/metricas', function (Request $request) {
        $query = MetricaRed::with('dispositivo:id,nombre,ip');

        // Filtro opcional por dispositivo
        if ($request->has('dispositivo_id')) {
            $query->where('dispositivo_id', $request->dispositivo_id);
        }

        // Filtro opcional por rango de fechas (útil para Grafana)
        if ($request->has('from') && $request->has('to')) {
            $from = date('Y-m-d H:i:s', strtotime($request->from));
            $to = date('Y-m-d H:i:s', strtotime($request->to));
            $query->whereBetween('fecha_registro', [$from, $to]);
        }

        $metricas = $query->orderBy('fecha_registro', 'desc')
                          ->limit($request->get('limit', 1000))
                          ->get();

        return response()->json($metricas);
    });
});
