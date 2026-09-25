<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Dispositivo;

class CdpDebugCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cdp:debug';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Muestra un diagnóstico de los vecinos CDP devueltos vs los dispositivos en la base de datos para depurar coincidencias.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("========================================");
        $this->info("   DIAGNÓSTICO DE EMPAREJAMIENTO CDP    ");
        $this->info("========================================\n");

        $scriptPath = base_path('test_cdp.py');
        if (!file_exists($scriptPath)) {
            $this->error('No se encontró test_cdp.py en la raíz del proyecto.');
            return;
        }

        $this->line("1. Ejecutando test_cdp.py...");
        $pythonBin = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
        $output = shell_exec("{$pythonBin} " . escapeshellarg($scriptPath) . ' 2>&1');

        if (empty($output)) {
            $this->error('El script no produjo ninguna salida.');
            return;
        }

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

        if ($json === null || !isset($json['status']) || $json['status'] !== 'success') {
            $this->error("Fallo al interpretar el JSON del script CDP. Salida bruta:");
            $this->line($output);
            return;
        }

        // 1. Mostrar Vecinos CDP devueltos
        $this->warn("\n---> VECINOS REPORTADOS POR CDP (test_cdp.py)");
        $this->line("Core IP: {$json['switch_core']}");
        $this->line("--------------------------------------------------");
        
        $adyacencias = $json['adyacencias'] ?? [];
        if (count($adyacencias) > 0) {
            foreach ($adyacencias as $index => $vecino) {
                $this->line(" " . str_pad("[".($index + 1)."]", 5) . " => '{$vecino}'");
            }
        } else {
            $this->line(" (No se encontraron vecinos CDP)");
        }

        // 2. Mostrar Dispositivos en Base de Datos
        $this->info("\n---> EQUIPOS ACTUALES EN BASE DE DATOS (tabla dispositivos)");
        $this->line("--------------------------------------------------");
        $dispositivos = Dispositivo::all(['id', 'nombre', 'ip']);
        
        if ($dispositivos->count() > 0) {
            // Formatear cabecera de tabla
            $this->line(" " . str_pad("ID", 5) . " | " . str_pad("IP", 15) . " | NOMBRE DB");
            $this->line(" " . str_repeat("-", 60));
            
            foreach ($dispositivos as $disp) {
                $this->line(" " . str_pad($disp->id, 5) . " | " . str_pad($disp->ip, 15) . " | '{$disp->nombre}'");
            }
        } else {
            $this->line(" (La tabla de dispositivos está vacía)");
        }

        $this->line("\n========================================");
        $this->line(" INSTRUCCIONES:");
        $this->line(" 1. Compara las cadenas entre comillas simples '' de ambas listas.");
        $this->line(" 2. El comando actual cdp:sync intenta buscar haciendo: LIKE %VecinoCDP% (quitando el dominio).");
        $this->line(" 3. Si hay un prefijo, sufijo o formato completamente distinto, el MATCH fallará.");
        $this->line("========================================\n");
    }
}
