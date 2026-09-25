<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$dispositivos = App\Models\Dispositivo::all();
$latestMetricsForAvg = collect();
foreach ($dispositivos as $disp) {
    $latest = $disp->metricas()->orderByDesc('fecha_registro')->first();
    if ($latest) {
        $latestMetricsForAvg->push($latest);
    }
}

$avgPing = $latestMetricsForAvg->where('ping_ms', '>', 0)->avg('ping_ms') ?? 0;
$avgPacketLoss = $latestMetricsForAvg->avg('packet_loss') ?? 0;
$avgCpu = $latestMetricsForAvg->avg('cpu_usage') ?? 0;

echo "Dispositivos en DB: " . $dispositivos->count() . "\n";
echo "Métricas activas encontradas: " . $latestMetricsForAvg->count() . "\n";
echo "Avg Ping: " . round($avgPing, 2) . " ms\n";
echo "Avg Packet Loss: " . round($avgPacketLoss, 2) . " %\n";
echo "Avg CPU: " . round($avgCpu, 2) . " %\n";
