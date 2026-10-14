<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

/**
 * Auto-descubrimiento de red como JOB ASÍNCRONO.
 *
 *  POST /topologia/descubrir                -> lanza el crawler desacoplado y devuelve job_id (202)
 *  GET  /topologia/descubrir/{job}          -> progreso en vivo (logs, contadores, fase)
 *  POST /topologia/descubrir/{job}/cancelar -> cancelación cooperativa + kill de respaldo
 *  POST /topologia/descubrir/probar         -> valida semilla + comunidad SNMP (~1 s)
 *
 * El servidor de desarrollo de PHP en Windows atiende con un solo worker, por lo que
 * una petición síncrona de minutos congelaba toda la aplicación. Con este diseño la
 * petición regresa de inmediato y el navegador solo hace polling ligero del estado.
 */
class DiscoveryController extends Controller
{
    private const JOB_DIR = 'app/discovery';
    private const STALE_SECONDS = 25;

    /**
     * Inicia un rastreo en segundo plano.
     */
    public function run(Request $request)
    {
        $validated = $request->validate([
            'method'        => 'required|in:cdp_lldp,cidr_sweep',
            'seed_ip'       => 'required_if:method,cdp_lldp|nullable|ipv4',
            'cidr'          => ['required_if:method,cidr_sweep', 'nullable', 'string', 'max:18', 'regex:/^\d{1,3}(\.\d{1,3}){3}\/(2[2-9]|3[0-2])$/'],
            'community'     => 'nullable|string|max:64',
            'max_depth'     => 'nullable|integer|min:1|max:6',
            'concurrency'   => 'nullable|integer|min:4|max:64',
            'probe_timeout' => 'nullable|numeric|min:0.5|max:5',
            'timeout'       => 'nullable|integer|min:30|max:600',
            'include_endpoints' => 'nullable|boolean',
        ], [
            'cidr.regex' => 'El CIDR debe tener formato A.B.C.D/NN con prefijo entre /22 y /32.',
            'seed_ip.required_if' => 'Indica la IP semilla del equipo raíz.',
            'seed_ip.ipv4' => 'La IP semilla no es una dirección IPv4 válida.',
        ]);

        // Evitar rastreos simultáneos que compitan por los mismos equipos
        if ($active = $this->findActiveJob()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya hay un rastreo en curso. Puedes seguir su progreso o cancelarlo.',
                'job_id'  => $active,
                'running' => true,
            ], 409);
        }

        $jobId = now()->format('Ymd_His') . '_' . Str::lower(Str::random(6));
        $dir = $this->jobDir();
        $jobFile = $dir . DIRECTORY_SEPARATOR . $jobId . '.json';
        $cancelFile = $dir . DIRECTORY_SEPARATOR . $jobId . '.cancel';

        $method = $validated['method'];
        $args = [
            base_path('worker/network_discovery.py'),
            '--method', $method,
            '--community', ($validated['community'] ?? '') !== '' ? $validated['community'] : 'public',
            '--max-depth', (string)($validated['max_depth'] ?? 3),
            '--concurrency', (string)($validated['concurrency'] ?? 24),
            '--probe-timeout', (string)($validated['probe_timeout'] ?? 1.2),
            '--timeout', (string)($validated['timeout'] ?? 180),
            '--job-file', $jobFile,
            '--cancel-file', $cancelFile,
        ];
        if ($method === 'cdp_lldp') {
            array_push($args, '--seed', $validated['seed_ip']);
        } else {
            array_push($args, '--cidr', $validated['cidr']);
        }
        if (!empty($validated['include_endpoints'])) {
            $args[] = '--include-endpoints';
        }

        // Estado inicial para que el primer polling no devuelva 404
        file_put_contents($jobFile, json_encode([
            'job_id' => $jobId, 'status' => 'starting', 'phase' => 'init',
            'phase_label' => 'Lanzando motor de descubrimiento', 'updated_at' => microtime(true),
            'elapsed_seconds' => 0, 'logs' => [], 'counters' => new \stdClass, 'progress' => new \stdClass,
        ]));

        try {
            $this->launchDetached($this->getPythonBinary(), $args);
        } catch (\Throwable $e) {
            Log::error('No se pudo lanzar el crawler: ' . $e->getMessage());
            @unlink($jobFile);
            return response()->json([
                'success' => false,
                'message' => 'No se pudo lanzar el motor de descubrimiento: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json(['success' => true, 'job_id' => $jobId], 202);
    }

    /**
     * Estado/progreso de un job.
     */
    public function status(string $job)
    {
        $file = $this->jobFile($job);
        if (!$file || !is_file($file)) {
            return response()->json(['success' => false, 'message' => 'Job no encontrado.'], 404);
        }

        $state = $this->readJson($file);
        if (!$state) {
            // El worker está reemplazando el archivo en este instante
            return response()->json(['success' => true, 'job_id' => $job, 'status' => 'running', 'transient' => true]);
        }

        // Detección de procesos muertos (crash, kill externo, reinicio del equipo)
        $isActive = in_array($state['status'] ?? '', ['starting', 'running'], true);
        $age = microtime(true) - (float)($state['updated_at'] ?? 0);
        if ($isActive && $age > self::STALE_SECONDS) {
            $state['status'] = 'failed';
            $state['message'] = 'El proceso de descubrimiento dejó de responder.';
            $state['error'] = ($state['status'] ?? '') === 'starting'
                ? 'Python no arrancó. Verifica PYTHON_PATH en .env y que pysnmp/mysql-connector estén instalados.'
                : 'Sin latido del worker en ' . (int)$age . ' s.';
            @file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE));
        }

        $state['success'] = true;
        return response()->json($state, 200, [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Cancela un job en curso (señal cooperativa + taskkill de respaldo).
     */
    public function cancel(string $job)
    {
        $file = $this->jobFile($job);
        if (!$file || !is_file($file)) {
            return response()->json(['success' => false, 'message' => 'Job no encontrado.'], 404);
        }
        touch(substr($file, 0, -5) . '.cancel');

        // Dar 3 s al worker para cerrar ordenadamente y guardar parciales
        $state = null;
        for ($i = 0; $i < 15; $i++) {
            usleep(200000);
            $state = $this->readJson($file);
            if ($state && !in_array($state['status'] ?? '', ['starting', 'running'], true)) {
                return response()->json(['success' => true, 'status' => $state['status']]);
            }
        }

        $pid = (int)($state['pid'] ?? 0);
        if ($pid > 0) {
            if (PHP_OS_FAMILY === 'Windows') {
                @exec('taskkill /PID ' . $pid . ' /T /F 2>NUL');
            } elseif (function_exists('posix_kill')) {
                @posix_kill($pid, 9);
            }
        }
        $state = $state ?: [];
        $state['status'] = 'cancelled';
        $state['message'] = 'Rastreo cancelado (proceso finalizado forzosamente).';
        $state['updated_at'] = microtime(true);
        @file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE));

        return response()->json(['success' => true, 'status' => 'cancelled']);
    }

    /**
     * Prueba rápida de semilla + comunidad (sysName/sysDescr/uptime).
     */
    public function probe(Request $request)
    {
        $validated = $request->validate([
            'ip' => 'required|ipv4',
            'community' => 'nullable|string|max:64',
        ]);

        $process = new Process([
            $this->getPythonBinary(), base_path('worker/network_discovery.py'), '--probe',
            '--seed', $validated['ip'],
            '--community', ($validated['community'] ?? '') !== '' ? $validated['community'] : 'public',
        ], base_path(), $this->getProcessEnv());
        $process->setTimeout(15);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Tiempo de espera agotado consultando el equipo.'], 200);
        }

        $lines = array_filter(array_map('trim', explode("\n", $process->getOutput())));
        $data = json_decode((string)end($lines), true);
        if (!is_array($data)) {
            return response()->json([
                'ok' => false,
                'error' => 'Salida inválida del motor: ' . Str::limit(trim($process->getErrorOutput() ?: $process->getOutput()), 200),
            ], 200);
        }
        return response()->json($data, 200, [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    }

    // ------------------------------------------------------------------
    //  Helpers de jobs
    // ------------------------------------------------------------------

    private function jobDir(): string
    {
        $dir = storage_path(self::JOB_DIR);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function jobFile(string $job): ?string
    {
        if (!preg_match('/^\d{8}_\d{6}_[a-z0-9]{6}$/', $job)) {
            return null;
        }
        return $this->jobDir() . DIRECTORY_SEPARATOR . $job . '.json';
    }

    private function readJson(string $file): ?array
    {
        for ($i = 0; $i < 3; $i++) {
            $raw = @file_get_contents($file);
            if ($raw !== false && $raw !== '') {
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    return $data;
                }
            }
            usleep(50000);
        }
        return null;
    }

    private function findActiveJob(): ?string
    {
        $files = glob($this->jobDir() . DIRECTORY_SEPARATOR . '*.json') ?: [];
        rsort($files);
        foreach (array_slice($files, 0, 5) as $f) {
            $s = $this->readJson($f);
            if ($s && in_array($s['status'] ?? '', ['starting', 'running'], true)
                && (microtime(true) - (float)($s['updated_at'] ?? 0)) < self::STALE_SECONDS) {
                return basename($f, '.json');
            }
        }
        // Limpieza de jobs con más de 2 días
        foreach ($files as $f) {
            if (filemtime($f) < time() - 172800) {
                @unlink($f);
                @unlink(substr($f, 0, -5) . '.cancel');
            }
        }
        return null;
    }

    /**
     * Lanza un proceso totalmente desacoplado de la petición HTTP.
     * En Windows se usa Start-Process (sin heredar el socket del cliente),
     * de modo que la respuesta se entrega de inmediato.
     */
    private function launchDetached(string $bin, array $args): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $q = fn(string $s) => "'" . str_replace("'", "''", $s) . "'";
            // Start-Process une ArgumentList con espacios: cada argumento va entre comillas dobles
            $argList = implode(',', array_map(fn($a) => $q('"' . str_replace('"', '\\"', $a) . '"'), $args));
            $ps = 'Start-Process -FilePath ' . $q($bin)
                . ' -ArgumentList ' . $argList
                . ' -WorkingDirectory ' . $q(base_path())
                . ' -WindowStyle Hidden';
            $process = new Process(['powershell', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', $ps],
                base_path(), $this->getProcessEnv());
            $process->setTimeout(20);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Start-Process falló');
            }
            return;
        }

        $cmd = 'nohup ' . escapeshellarg($bin) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' > /dev/null 2>&1 &';
        exec($cmd);
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
