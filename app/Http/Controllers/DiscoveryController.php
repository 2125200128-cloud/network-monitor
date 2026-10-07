<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class DiscoveryController extends Controller
{
    /**
     * Ejecuta el crawler de auto-descubrimiento de red en segundo plano.
     */
    public function run(Request $request)
    {
        set_time_limit(0);
        
        $validated = $request->validate([
            'seed_ip' => 'nullable|ip',
            'community' => 'nullable|string|max:64',
            'method' => 'required|in:cdp_lldp,cidr_sweep',
            'cidr' => 'nullable|string|max:32',
            'max_depth' => 'nullable|integer|min:1|max:5'
        ]);

        $seedIp = $validated['seed_ip'] ?? '192.168.1.254';
        $community = !empty($validated['community']) ? $validated['community'] : 'public';
        $method = $validated['method'];
        $cidr = $validated['cidr'] ?? '192.168.1.0/24';
        $maxDepth = $validated['max_depth'] ?? 3;

        // Construir argumentos para el script Python
        $pythonBin = $this->getPythonBinary();
        $scriptPath = base_path('worker/network_discovery.py');
        $command = [
            $pythonBin,
            $scriptPath,
            '--method', $method,
            '--community', $community,
            '--max-depth', (string)$maxDepth,
            '--json'
        ];

        if ($method === 'cdp_lldp') {
            $command[] = '--seed';
            $command[] = $seedIp;
        } else {
            $command[] = '--cidr';
            $command[] = $cidr;
        }

        try {
            $process = new Process($command, base_path(), $this->getProcessEnv());
            // Timeout de 5 minutos para permitir escaneos profundos en redes grandes
            $process->setTimeout(300);
            $process->run();

            $output = mb_convert_encoding($process->getOutput(), 'UTF-8', 'UTF-8');
            $errorOutput = mb_convert_encoding($process->getErrorOutput(), 'UTF-8', 'UTF-8');

            // Extraer bloque JSON estructurado
            $parsedData = null;
            if (str_contains($output, '---JSON_OUTPUT_START---')) {
                $start = strpos($output, '---JSON_OUTPUT_START---') + strlen('---JSON_OUTPUT_START---');
                $end = strpos($output, '---JSON_OUTPUT_END---');
                if ($end > $start) {
                    $jsonStr = trim(substr($output, $start, $end - $start));
                    $parsedData = json_decode($jsonStr, true);
                }
            }

            if (!$process->isSuccessful() && !$parsedData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error durante la ejecución del crawler de red.',
                    'error' => $errorOutput ?: $output
                ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            }

            $totalDevices = $parsedData['total_devices'] ?? 0;
            $newDevices = $parsedData['new_devices'] ?? 0;
            $totalLinks = $parsedData['total_links'] ?? 0;
            $newLinks = $parsedData['new_links'] ?? 0;
            $elapsed = $parsedData['elapsed_seconds'] ?? 0;

            $summaryMsg = "Auto-descubrimiento finalizado ({$elapsed}s): {$totalDevices} equipos ({$newDevices} nuevos), {$totalLinks} enlaces detectados.";

            return response()->json([
                'success' => true,
                'message' => $summaryMsg,
                'data' => $parsedData,
                'raw_output' => $output
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            Log::error('Excepción en auto-descubrimiento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Excepción en el servidor al ejecutar el escaneo.',
                'error' => $e->getMessage()
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Resuelve el binario ejecutable de Python disponible en el entorno.
     */
    private function getPythonBinary(): string
    {
        $localAppData = getenv('LOCALAPPDATA') ?: '';
        $specificPaths = [
            env('PYTHON_PATH'),
            $localAppData ? $localAppData . '\\Programs\\Python\\Python312\\python.exe' : '',
            $localAppData ? $localAppData . '\\Programs\\Python\\Python311\\python.exe' : '',
            $localAppData ? $localAppData . '\\Python\\pythoncore-3.14-64\\python.exe' : '',
        ];
        foreach ($specificPaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }
        return 'python';
    }

    /**
     * Devuelve las variables de entorno del sistema necesarias para sockets en Windows.
     */
    private function getProcessEnv(): array
    {
        $systemRoot = getenv('SystemRoot') ?: ($_SERVER['SystemRoot'] ?? 'C:\\Windows');
        $path = getenv('PATH') ?: ($_SERVER['PATH'] ?? '');
        $windir = getenv('WINDIR') ?: ($_SERVER['WINDIR'] ?? $systemRoot);
        $temp = getenv('TEMP') ?: ($_SERVER['TEMP'] ?? 'C:\\Windows\\Temp');
        $localAppData = getenv('LOCALAPPDATA') ?: ($_SERVER['LOCALAPPDATA'] ?? '');
        $appData = getenv('APPDATA') ?: ($_SERVER['APPDATA'] ?? '');
        $comspec = getenv('COMSPEC') ?: ($_SERVER['COMSPEC'] ?? 'C:\\Windows\\system32\\cmd.exe');

        return array_merge($_ENV, $_SERVER, [
            'SystemRoot' => $systemRoot,
            'SYSTEMROOT' => $systemRoot,
            'PATH' => $path,
            'WINDIR' => $windir,
            'TEMP' => $temp,
            'LOCALAPPDATA' => $localAppData,
            'APPDATA' => $appData,
            'COMSPEC' => $comspec,
        ]);
    }

    /**
     * Detecta la interfaz Ethernet por cable física (RJ-45) y barre la subred local.
     */
    public function scanLocalInterface(Request $request)
    {
        set_time_limit(0);
        
        $community = $request->input('community', 'public');
        $threads = (int)$request->input('threads', 50);
        $subnet = trim((string)$request->input('subnet', $request->input('target', '')));

        $pythonBin = $this->getPythonBinary();
        $scriptPath = base_path('worker/local_interface_scanner.py');
        $command = [
            $pythonBin,
            $scriptPath,
            '--community', $community,
            '--threads', (string)$threads,
            '--json'
        ];

        if (!empty($subnet)) {
            $command[] = '--subnet';
            $command[] = $subnet;
        }

        try {
            $process = new Process($command, base_path(), $this->getProcessEnv());
            $process->setTimeout(60);
            $process->run();

            $output = mb_convert_encoding($process->getOutput(), 'UTF-8', 'UTF-8');
            $errorOutput = mb_convert_encoding($process->getErrorOutput(), 'UTF-8', 'UTF-8');

            $parsedData = null;
            if (str_contains($output, '---JSON_OUTPUT_START---')) {
                $start = strpos($output, '---JSON_OUTPUT_START---') + strlen('---JSON_OUTPUT_START---');
                $end = strpos($output, '---JSON_OUTPUT_END---');
                if ($end > $start) {
                    $jsonStr = trim(substr($output, $start, $end - $start));
                    $parsedData = json_decode($jsonStr, true);
                }
            }

            if (!$parsedData) {
                Log::error('Error en escaneo local: ' . $output . ' | STDERR: ' . $errorOutput);
                $detail = !empty($errorOutput) ? substr($errorOutput, 0, 150) : (!empty($output) ? substr($output, 0, 150) : 'Sin salida del proceso.');
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener la telemetría de la interfaz local.',
                    'error' => $detail,
                    'raw_output' => $output
                ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            }

            $adapter = $parsedData['adapter'] ?? [];
            $totalDevices = $parsedData['total_devices'] ?? 0;
            $newDevices = $parsedData['new_devices'] ?? 0;
            $totalLinks = $parsedData['total_links'] ?? 0;
            $elapsed = $parsedData['elapsed_seconds'] ?? 0;

            if (($parsedData['status'] ?? '') === 'warning' || empty($adapter['ip'])) {
                return response()->json([
                    'success' => false,
                    'is_warning' => true,
                    'message' => $parsedData['message'] ?? 'Cable de red RJ-45 desconectado o sin dirección IP asignada.',
                    'data' => $parsedData,
                    'raw_output' => $output
                ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            }

            $adapterName = $adapter['name'] ?? 'Ethernet';
            $localIp = $adapter['ip'] ?? 'No detectada';
            $gw = $adapter['gateway'] ?? 'Sin Gateway';

            $summaryMsg = "Escaneo local finalizado ({$elapsed}s): Interfaz '{$adapterName}' [{$localIp} -> Gateway: {$gw}]. {$totalDevices} equipos gestionables ({$newDevices} nuevos), {$totalLinks} enlaces mapeados.";

            return response()->json([
                'success' => true,
                'message' => $summaryMsg,
                'data' => $parsedData,
                'raw_output' => $output
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            Log::error('Excepción en escaneo local: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error ejecutando el escaneo de interfaz física: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500, [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        }
    }
}
