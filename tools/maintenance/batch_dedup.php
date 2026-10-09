<?php
require_once __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== INICIANDO PURGA INDEXADA Y SWAP DE TABLAS ===\n";
$start = microtime(true);

// 1. TELEMETRIA_CHASIS (172 dispositivos)
echo "1. Optimizando telemetria_chasis...\n";
DB::statement("DROP TABLE IF EXISTS telemetria_chasis_clean");
DB::statement("CREATE TABLE telemetria_chasis_clean LIKE telemetria_chasis");

// Obtener los IDs de dispositivo
$dispositivoIds = DB::table('dispositivos')->pluck('id');
echo "   Procesando " . count($dispositivoIds) . " dispositivos...\n";

$chasisMaxIds = [];
foreach ($dispositivoIds as $did) {
    $maxId = DB::table('telemetria_chasis')->where('dispositivo_id', $did)->max('id');
    if ($maxId) {
        $chasisMaxIds[] = $maxId;
    }
}

if (!empty($chasisMaxIds)) {
    foreach (array_chunk($chasisMaxIds, 100) as $chunk) {
        DB::statement("
            INSERT INTO telemetria_chasis_clean
            SELECT * FROM telemetria_chasis WHERE id IN (" . implode(',', $chunk) . ")
        ");
    }
}

$cleanChasisCount = DB::table('telemetria_chasis_clean')->count();
echo "   -> Filas limpias en telemetria_chasis_clean: {$cleanChasisCount}\n";

try {
    DB::statement("ALTER TABLE telemetria_chasis_clean ADD UNIQUE KEY uq_disp_id (dispositivo_id)");
    echo "   -> UNIQUE KEY (dispositivo_id) creado.\n";
} catch (\Exception $e) {
    echo "   -> Nota: " . $e->getMessage() . "\n";
}

DB::statement("DROP TABLE IF EXISTS telemetria_chasis_old");
DB::statement("RENAME TABLE telemetria_chasis TO telemetria_chasis_old, telemetria_chasis_clean TO telemetria_chasis");
DB::statement("DROP TABLE IF EXISTS telemetria_chasis_old");
echo "   -> telemetria_chasis sustituida con éxito.\n";


// 2. TELEMETRIA_INTERFACES
echo "2. Optimizando telemetria_interfaces...\n";
DB::statement("DROP TABLE IF EXISTS telemetria_interfaces_clean");
DB::statement("CREATE TABLE telemetria_interfaces_clean LIKE telemetria_interfaces");

$ifaceIds = DB::table('interfaces_red')->pluck('id')->toArray();
$totalIfaces = count($ifaceIds);
echo "   Procesando {$totalIfaces} interfaces de red...\n";

$inserted = 0;
// Chunk by 200 interfaces
foreach (array_chunk($ifaceIds, 200) as $chunk) {
    $maxIds = DB::table('telemetria_interfaces')
        ->whereIn('interfaz_id', $chunk)
        ->select(DB::raw('MAX(id) as max_id'))
        ->groupBy('interfaz_id')
        ->pluck('max_id')
        ->filter()
        ->toArray();

    if (!empty($maxIds)) {
        DB::statement("
            INSERT INTO telemetria_interfaces_clean
            SELECT * FROM telemetria_interfaces WHERE id IN (" . implode(',', $maxIds) . ")
        ");
        $inserted += count($maxIds);
    }
}

echo "   -> Filas limpias insertadas: {$inserted}\n";

try {
    DB::statement("ALTER TABLE telemetria_interfaces_clean ADD UNIQUE KEY uq_interfaz_id (interfaz_id)");
    echo "   -> UNIQUE KEY (interfaz_id) creado.\n";
} catch (\Exception $e) {
    echo "   -> Nota: " . $e->getMessage() . "\n";
}

DB::statement("DROP TABLE IF EXISTS telemetria_interfaces_old");
DB::statement("RENAME TABLE telemetria_interfaces TO telemetria_interfaces_old, telemetria_interfaces_clean TO telemetria_interfaces");
DB::statement("DROP TABLE IF EXISTS telemetria_interfaces_old");
echo "   -> telemetria_interfaces sustituida con éxito.\n";

$elapsed = round(microtime(true) - $start, 2);
echo "=== PURGA Y OPTIMIZACION COMPLETADA CON EXITO EN {$elapsed}s ===\n";
