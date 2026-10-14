<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dispositivo;
use App\Models\AuditoriaComando;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ConsolaSeguraController extends Controller
{
    /**
     * Comandos de solo lectura permitidos (prefijos, ya normalizados a minúsculas).
     * Cualquier comando que modifique configuración o estado del equipo se rechaza.
     */
    private const COMANDOS_PERMITIDOS = [
        'show ', 'get ', 'ping ', 'traceroute ',
        'diagnose sys session stat', 'diagnose vpn tunnel list',
    ];

    private const COMANDOS_EXACTOS = ['show', 'get system status'];

    public function index(Request $request)
    {
        $dispositivos = Dispositivo::with(['telemetriaChasis', 'ultimaMetrica'])
            ->orderBy('nombre')
            ->get();

        $selectedId = $request->query('dispositivo_id') ?: ($dispositivos->first()?->id ?? null);
        $dispositivoSeleccionado = $selectedId ? Dispositivo::find($selectedId) : $dispositivos->first();

        $dispositivosData = $dispositivos->map(function ($d) {
            return [
                'id' => $d->id,
                'nombre' => $d->nombre,
                'ip' => $d->ip,
                'tipo' => $d->tipo ?: 'Switch',
                'estado' => $d->estado,
                'ssh_user' => $d->ssh_user ?: 'admin',
                'ssh_port' => $d->ssh_port ?: 22,
                'has_password' => !empty($d->ssh_password_encrypted),
            ];
        });

        $historial = AuditoriaComando::with(['dispositivo', 'user'])
            ->orderBy('fecha_ejecucion', 'desc')
            ->take(15)
            ->get();

        return view('consola.index', compact('dispositivos', 'dispositivosData', 'dispositivoSeleccionado', 'historial'));
    }

    /**
     * Probar conexión SSH y autenticación con el equipo físico.
     */
    public function probarConexion(Request $request)
    {
        $request->validate([
            'dispositivo_id' => 'required|exists:dispositivos,id',
            'ssh_user' => 'nullable|string|max:100',
            'ssh_password' => 'nullable|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
        ]);

        $dispositivo = Dispositivo::findOrFail($request->dispositivo_id);
        [$user, $port, $password, $error] = $this->resolverCredenciales($request, $dispositivo);
        if ($error) {
            return response()->json(['success' => false, 'message' => $error, 'error' => $error], 422);
        }

        $testCommand = str_contains(strtolower($dispositivo->nombre), 'forti') ? 'get system status' : 'show version';
        $resultado = $this->ejecutarSsh($dispositivo, $user, $port, $password, $testCommand);

        if (!$resultado['ok']) {
            return response()->json(['success' => false, 'message' => $resultado['error'], 'error' => $resultado['error'], 'real_ssh' => false], 422);
        }

        // Solo se guardan credenciales que ya demostraron funcionar
        if ($request->boolean('guardar_credenciales') && $request->filled('ssh_password')) {
            $dispositivo->ssh_user = $user;
            $dispositivo->ssh_port = $port;
            $dispositivo->ssh_password_encrypted = Crypt::encryptString($request->ssh_password);
            $dispositivo->save();
        }

        return response()->json([
            'success' => true,
            'message' => "Conexión SSH exitosa a {$dispositivo->ip}:{$port} con usuario '{$user}'.",
            'output' => $resultado['output'],
            'real_ssh' => true,
        ]);
    }

    /**
     * Guardar credenciales SSH de un dispositivo de red.
     */
    public function guardarCredenciales(Request $request)
    {
        $request->validate([
            'dispositivo_id' => 'required|exists:dispositivos,id',
            'ssh_user' => 'required|string|max:100',
            'ssh_password' => 'required|string|max:255',
            'ssh_port' => 'required|integer|min:1|max:65535',
        ]);

        $dispositivo = Dispositivo::findOrFail($request->dispositivo_id);
        $dispositivo->ssh_user = $request->ssh_user;
        $dispositivo->ssh_port = $request->ssh_port;
        $dispositivo->ssh_password_encrypted = Crypt::encryptString($request->ssh_password);
        $dispositivo->save();

        return response()->json([
            'success' => true,
            'message' => "Credenciales SSH guardadas exitosamente para {$dispositivo->nombre} ({$dispositivo->ip})."
        ]);
    }

    /**
     * Ejecuta un comando de solo lectura en el equipo real vía SSH.
     * Nunca se devuelve salida simulada: si no hay conexión, se informa el error.
     */
    public function ejecutar(Request $request)
    {
        $request->validate([
            'dispositivo_id' => 'required|exists:dispositivos,id',
            'comando' => 'required|string|max:255',
            'ssh_user' => 'nullable|string|max:100',
            'ssh_password' => 'nullable|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
        ]);

        $dispositivo = Dispositivo::findOrFail($request->dispositivo_id);
        $comandoRaw = trim($request->comando);
        $clientIp = $request->ip() ?: '127.0.0.1';
        $comando = $this->normalizarComando($comandoRaw);

        if ($comando === 'help') {
            return response()->json(['output' => $this->textoAyuda(), 'real_ssh' => false]);
        }

        if (preg_match('/[;|&`$><\r\n]/', $comandoRaw) || !$this->comandoPermitido($comando)) {
            $this->registrarAuditoria($dispositivo->id, $comandoRaw, $clientIp, 'bloqueado', 'Comando fuera de la lista de solo lectura.');
            return response()->json([
                'error' => "Comando bloqueado: la consola solo permite comandos de lectura (show, get, ping, traceroute). Escriba 'help' para ver la lista.",
            ], 403);
        }

        [$user, $port, $password, $error] = $this->resolverCredenciales($request, $dispositivo);
        if ($error) {
            $this->registrarAuditoria($dispositivo->id, $comandoRaw, $clientIp, 'fallo_conexion', $error);
            return response()->json(['error' => $error], 422);
        }

        $resultado = $this->ejecutarSsh($dispositivo, $user, $port, $password, $comando);
        if (!$resultado['ok']) {
            $this->registrarAuditoria($dispositivo->id, $comandoRaw, $clientIp, 'fallo_conexion', $resultado['error']);
            return response()->json(['error' => $resultado['error']], 502);
        }

        $this->registrarAuditoria($dispositivo->id, $comandoRaw, $clientIp, 'autorizado', $resultado['output']);
        return response()->json([
            'output' => $resultado['output'],
            'real_ssh' => true,
            'session_info' => "SSH {$dispositivo->ip}:{$port} ({$user})",
        ]);
    }

    /**
     * Credenciales: las del formulario o las guardadas cifradas. Sin contraseña no se intenta conectar.
     *
     * @return array{0:string,1:int,2:?string,3:?string} [usuario, puerto, contraseña, error]
     */
    private function resolverCredenciales(Request $request, Dispositivo $dispositivo): array
    {
        $user = $request->filled('ssh_user') ? $request->ssh_user : ($dispositivo->ssh_user ?: 'admin');
        $port = $request->filled('ssh_port') ? (int) $request->ssh_port : ($dispositivo->ssh_port ?: 22);

        if ($request->filled('ssh_password')) {
            return [$user, $port, $request->ssh_password, null];
        }
        if (!empty($dispositivo->ssh_password_encrypted)) {
            try {
                return [$user, $port, Crypt::decryptString($dispositivo->ssh_password_encrypted), null];
            } catch (DecryptException) {
                return [$user, $port, null, 'La contraseña SSH guardada no se puede descifrar (¿cambió APP_KEY?). Vuelva a capturarla en [Credenciales SSH].'];
            }
        }
        return [$user, $port, null, "No hay credenciales SSH configuradas para {$dispositivo->nombre}. Captúrelas en [Credenciales SSH]."];
    }

    /**
     * @return array{ok:bool, output?:string, error?:string}
     */
    private function ejecutarSsh(Dispositivo $dispositivo, string $user, int $port, string $password, string $comando): array
    {
        $env = [
            'SYSTEMROOT' => getenv('SystemRoot') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
            'PATH' => getenv('PATH') ?: 'C:\\Windows\\system32;C:\\Windows',
            'TEMP' => getenv('TEMP') ?: 'C:\\Windows\\Temp',
            'TMP' => getenv('TMP') ?: 'C:\\Windows\\Temp',
            // La contraseña viaja por entorno, no en la línea de comandos (visible en la lista de procesos)
            'NOC_SSH_PASSWORD' => $password,
        ];

        $process = new Process([
            env('PYTHON_PATH', 'python'),
            base_path('worker/ssh_executor.py'),
            $dispositivo->ip,
            (string) $port,
            $user,
            $comando,
        ], base_path(), $env);
        $process->setTimeout(25);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return ['ok' => false, 'error' => "Tiempo de espera agotado conectando por SSH a {$dispositivo->ip}:{$port}."];
        }

        $output = $process->getOutput();
        if ($process->isSuccessful() && trim($output) !== '') {
            return ['ok' => true, 'output' => $output];
        }

        $error = trim($process->getErrorOutput());
        return ['ok' => false, 'error' => $error !== '' ? $error : "Fallo en conexión SSH con {$dispositivo->ip}:{$port}."];
    }

    private function normalizarComando(string $comandoRaw): string
    {
        $c = strtolower(preg_replace('/\s+/', ' ', trim($comandoRaw)));

        $alias = [
            'show version' => ['sh ver', 'show ver', 'sh version'],
            'show ip interface brief' => ['sh ip int br', 'sh ip int brief', 'show ip int br', 'show ip int brief', 'sh ip interface brief'],
            'show interfaces status' => ['sh int status', 'show int status', 'sh interfaces status', 'sh int stat'],
            'show running-config' => ['sh run', 'show run', 'sh running-config'],
            'show vlan brief' => ['sh vlan', 'show vlan', 'sh vlan brief', 'show vlans'],
            'show mac address-table' => ['sh mac', 'show mac', 'sh mac-address-table', 'show mac-address-table', 'sh mac address-table'],
            'show cdp neighbors' => ['sh cdp', 'sh cdp nei', 'sh cdp neigh'],
            'show ip route' => ['sh ip route', 'sh route'],
            'get system status' => ['get sys stat', 'get status', 'status'],
            'get system performance status' => ['get sys perf', 'get performance'],
            'diagnose sys session stat' => ['diag sys session', 'session stat'],
            'diagnose vpn tunnel list' => ['diag vpn', 'vpn list'],
            'help' => ['?', 'commands', 'ayuda'],
        ];
        foreach ($alias as $canonico => $variantes) {
            if (in_array($c, $variantes, true)) {
                return $canonico;
            }
        }
        return $c;
    }

    private function comandoPermitido(string $comando): bool
    {
        if (in_array($comando, self::COMANDOS_EXACTOS, true)) {
            return true;
        }
        foreach (self::COMANDOS_PERMITIDOS as $prefijo) {
            if (str_starts_with($comando, $prefijo)) {
                return true;
            }
        }
        return false;
    }

    private function textoAyuda(): string
    {
        return "Consola de solo lectura. Se ejecuta por SSH en el equipo real.\n" .
               "Comandos permitidos:\n" .
               "  show ...                       (Cisco IOS / NX-OS)\n" .
               "  get ...                        (FortiOS)\n" .
               "  diagnose sys session stat      (FortiOS)\n" .
               "  diagnose vpn tunnel list       (FortiOS)\n" .
               "  ping <ip> / traceroute <ip>\n" .
               "  clear                          Limpiar la pantalla\n";
    }

    private function registrarAuditoria($dispositivoId, $comando, $ipOrigen, $estado, $salida)
    {
        AuditoriaComando::create([
            'user_id' => auth()->id(),
            'dispositivo_id' => $dispositivoId,
            'comando_solicitado' => $comando,
            'ip_origen' => $ipOrigen ?: '127.0.0.1',
            'estado_ejecucion' => in_array($estado, ['autorizado', 'bloqueado', 'fallo_conexion']) ? $estado : 'autorizado',
            'salida_terminal' => $salida,
        ]);
    }
}
