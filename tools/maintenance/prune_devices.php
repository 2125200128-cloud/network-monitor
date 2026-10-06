<?php

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Dispositivo;

echo "=== INICIANDO DEPURACIÓN Y DESATURACIÓN DE DISPOSITIVOS ===" . PHP_EOL;

$initialCount = Dispositivo::count();
echo "Dispositivos registrados actualmente: {$initialCount}" . PHP_EOL;

// 1. Identificar IDs de Teléfonos IP Cisco (SEP...) y Access Points (AP) y endpoints MAC
$phonesAndAps = Dispositivo::where('nombre', 'LIKE', 'SEP%')
    ->orWhere('nombre', 'LIKE', '%-AP%')
    ->orWhere('nombre', 'LIKE', 'AP-%')
    ->orWhere('nombre', '580a209e9d4d')
    ->pluck('id')
    ->toArray();

echo "Detectados " . count($phonesAndAps) . " teléfonos IP (SEP), Access Points y endpoints para depurar." . PHP_EOL;

if (!empty($phonesAndAps)) {
    // Eliminar enlaces donde participen estos dispositivos
    DB::table('enlaces_red')
        ->whereIn('origen_dispositivo_id', $phonesAndAps)
        ->orWhereIn('destino_dispositivo_id', $phonesAndAps)
        ->delete();

    // Obtener interfaces de estos dispositivos
    $ifaceIds = DB::table('interfaces_red')
        ->whereIn('dispositivo_id', $phonesAndAps)
        ->pluck('id')
        ->toArray();

    if (!empty($ifaceIds)) {
        DB::table('telemetria_interfaces')->whereIn('interfaz_id', $ifaceIds)->delete();
        DB::table('enlaces_red')
            ->whereIn('origen_interfaz_id', $ifaceIds)
            ->orWhereIn('destino_interfaz_id', $ifaceIds)
            ->delete();
        DB::table('interfaces_red')->whereIn('id', $ifaceIds)->delete();
    }

    DB::table('telemetria_chasis')->whereIn('dispositivo_id', $phonesAndAps)->delete();
    DB::table('configuraciones_dispositivo')->whereIn('dispositivo_id', $phonesAndAps)->delete();
    DB::table('tablas_dispositivo')->whereIn('dispositivo_id', $phonesAndAps)->delete();
    DB::table('auditoria_comandos')->whereIn('dispositivo_id', $phonesAndAps)->delete();

    // Eliminar los dispositivos de la tabla principal
    DB::table('dispositivos')->whereIn('id', $phonesAndAps)->delete();
}

// 2. Consolidar y depurar duplicados de switches Core
// Nexus7K_Core_Pri: Conservar ID 5 (10.4.155.2), migrar y remover IDs 18, 19, 23
$priDuplicates = [18, 19, 23];
foreach ($priDuplicates as $dupId) {
    if (Dispositivo::find($dupId)) {
        DB::table('enlaces_red')->where('origen_dispositivo_id', $dupId)->update(['origen_dispositivo_id' => 5]);
        DB::table('enlaces_red')->where('destino_dispositivo_id', $dupId)->update(['destino_dispositivo_id' => 5]);
        DB::table('telemetria_chasis')->where('dispositivo_id', $dupId)->delete();
        DB::table('interfaces_red')->where('dispositivo_id', $dupId)->delete();
        DB::table('dispositivos')->where('id', $dupId)->delete();
    }
}

// Nexus7K_Core_Sec: Conservar ID 17 (10.4.155.3), migrar y remover IDs 6, 7, 22
$secDuplicates = [6, 7, 22];
foreach ($secDuplicates as $dupId) {
    if (Dispositivo::find($dupId)) {
        DB::table('enlaces_red')->where('origen_dispositivo_id', $dupId)->update(['origen_dispositivo_id' => 17]);
        DB::table('enlaces_red')->where('destino_dispositivo_id', $dupId)->update(['destino_dispositivo_id' => 17]);
        DB::table('telemetria_chasis')->where('dispositivo_id', $dupId)->delete();
        DB::table('interfaces_red')->where('dispositivo_id', $dupId)->delete();
        DB::table('dispositivos')->where('id', $dupId)->delete();
    }
}

// Eliminar enlaces duplicados redundantes entre los mismos puertos/dispositivos
$links = DB::table('enlaces_red')->get();
$seen = [];
foreach ($links as $l) {
    $pairKey = min($l->origen_dispositivo_id, $l->destino_dispositivo_id) . '_' . 
               max($l->origen_dispositivo_id, $l->destino_dispositivo_id) . '_' . 
               $l->origen_interfaz_id . '_' . $l->destino_interfaz_id;
    if (isset($seen[$pairKey])) {
        DB::table('enlaces_red')->where('id', $l->id)->delete();
    } else {
        $seen[$pairKey] = true;
    }
}

$finalCount = Dispositivo::count();
echo "=== LIMPIEZA FINALIZADA ===" . PHP_EOL;
echo "Dispositivos conservados (solo switches y routers reales): {$finalCount}" . PHP_EOL;
echo "Enlaces físicos mapeados: " . DB::table('enlaces_red')->count() . PHP_EOL;

foreach (Dispositivo::all() as $d) {
    echo "- [ID {$d->id}] {$d->nombre} ({$d->ip}) [{$d->estado}]" . PHP_EOL;
}
