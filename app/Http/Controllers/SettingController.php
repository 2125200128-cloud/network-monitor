<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConfiguracionGeneral;
use App\Models\User;
use App\Services\AlertasTelegram;
use App\Services\TelegramBot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $settings = ConfiguracionGeneral::firstOrCreate([], [
            'intervalo_sondeo_segundos' => 60,
            'comunidad_snmp_default' => 'public',
            'ip_switch_core' => config('noc.ip_switch_core'),
            'umbral_cpu_warning' => 85,
            'umbral_loss_warning' => 5,
        ]);

        $users = User::all();
        $section = $request->query('section', 'general');
        $retencion = $this->estadoRetencion($settings);
        $token = (string) $settings->telegram_bot_token;
        $telegram = [
            'token_guardado' => $token !== '' ? '••••' . substr($token, -4) : null,
            'ultimo_error' => Cache::get('telegram_ultimo_error'),
            'notificadas_24h' => \App\Models\AlarmaRed::where('notificada_telegram_at', '>=', now()->subDay())->count(),
        ];

        $snmpv3 = [
            'configurado' => (bool) $settings->snmpv3_usuario,
            // Solo si hay valor guardado; nunca se envían al navegador
            'auth_guardada' => $settings->getRawOriginal('snmpv3_auth_pass') !== null,
            'priv_guardada' => $settings->getRawOriginal('snmpv3_priv_pass') !== null,
            // El FortiGate del centro de datos (inventario), si no, cualquier firewall monitoreado
            'ip_prueba' => \App\Models\InventarioCentroDatos::where('capa', 'perimetro')->value('ip')
                ?? \App\Models\Dispositivo::where('tipo_detectado', 'firewall')->value('ip') ?? '',
        ];

        $vcenter = [
            'configurado' => \App\Services\VcenterService::configurado(),
            'clave_guardada' => $settings->getRawOriginal('vcenter_password') !== null,
        ];
        $esxi = [
            'configurado' => $settings->esxi_usuario && $settings->getRawOriginal('esxi_password') !== null,
            'clave_guardada' => $settings->getRawOriginal('esxi_password') !== null,
            'ip_prueba' => \App\Models\Dispositivo::where('ubicacion', \App\Http\Controllers\VirtualizacionController::UBICACION)
                ->where('nombre', 'not like', '%vCenter%')->orderBy('nombre')->value('ip'),
        ];

        return view('settings.index', compact('settings', 'users', 'section', 'retencion', 'telegram', 'snmpv3', 'vcenter', 'esxi'));
    }

    /** Filas para la sección de retención: días efectivos, rango permitido y lo que hay guardado hoy. */
    private function estadoRetencion(ConfiguracionGeneral $settings): array
    {
        $tablas = [
            'metricas_red' => ['Métricas de cada sondeo', 'Ping, CPU, RAM y tráfico de cada equipo. Las pantallas usan la última hora.', 'metricas_red', 'fecha_registro', null],
            'global_metrics' => ['Gráfica de tendencias', 'Tráfico y latencia globales del dashboard (la gráfica llega a 7 días).', 'global_metrics', 'recorded_at', null],
            'metricas_red_hora' => ['Resumen por hora', 'Disponibilidad de 24 h / 7 d / 30 d por equipo.', 'metricas_red_hora', 'hora', null],
            'alarmas_resueltas' => ['Alarmas resueltas', 'Historial de incidentes, MTTR y MTBF. Las activas nunca se borran.', 'alarmas_red', 'fecha_resolucion', ['estado', 'resuelta']],
            'historico_servicio_web' => ['Historial de servicios web', 'Estado y tiempo de respuesta de cada sitio.', 'historico_servicio_web', 'created_at', null],
        ];
        $efectivos = ConfiguracionGeneral::retencionDias();

        $filas = [];
        foreach ($tablas as $clave => [$titulo, $descripcion, $tabla, $columna, $extra]) {
            [$campo, $min, $max] = ConfiguracionGeneral::RETENCION[$clave];
            $q = DB::table($tabla)->when($extra, fn ($q) => $q->where($extra[0], $extra[1]));
            $filas[] = [
                'campo' => $campo, 'titulo' => $titulo, 'descripcion' => $descripcion, 'min' => $min, 'max' => $max,
                'valor' => $settings->{$campo}, 'efectivo' => $efectivos[$clave],
                'predeterminado' => (int) config("noc.retencion_dias.{$clave}"),
                'registros' => (clone $q)->count(),
                'desde' => (clone $q)->min($columna),
            ];
        }
        return $filas;
    }

    public function update(Request $request)
    {
        $request->validate([
            'intervalo_sondeo_segundos' => 'required|integer|min:10',
            'comunidad_snmp_default' => 'required|string|max:255',
            'ip_switch_core' => 'required|ip',
            'umbral_cpu_warning' => 'required|integer|min:1|max:100',
            'umbral_loss_warning' => 'required|integer|min:1|max:100',
        ] + collect(ConfiguracionGeneral::RETENCION)->mapWithKeys(
            fn ($r) => [$r[0] => "nullable|integer|min:{$r[1]}|max:{$r[2]}"]
        )->all(), [
            'min' => 'El valor de :attribute debe ser al menos :min.',
            'max' => 'El valor de :attribute no puede ser mayor que :max.',
            'integer' => 'El valor de :attribute debe ser un número entero.',
        ], [
            'retencion_metricas_dias' => 'días de métricas de cada sondeo',
            'retencion_tendencias_dias' => 'días de la gráfica de tendencias',
            'retencion_consolidado_dias' => 'días del resumen por hora',
            'retencion_alarmas_dias' => 'días de alarmas resueltas',
            'retencion_servicios_web_dias' => 'días del historial de servicios web',
        ]);

        $settings = ConfiguracionGeneral::first();
        if ($settings) {
            // Solo los campos de este formulario: Telegram y SNMPv3 (secretos) tienen su propio guardado.
            // Los FortiGate se consultan con el usuario y contraseña de SNMPv3, no con una comunidad aparte.
            $campos = array_merge(['intervalo_sondeo_segundos', 'comunidad_snmp_default', 'ip_switch_core', 'umbral_cpu_warning', 'umbral_loss_warning'],
                array_column(ConfiguracionGeneral::RETENCION, 0));
            $settings->update($request->only($campos));
            // La próxima ejecución de noc:sync aplica la retención nueva sin esperar la hora
            Cache::forget('noc_retencion_hecha');
        }

        return redirect()->route('settings.index', ['section' => 'general'])->with('success', 'Configuraciones guardadas exitosamente.');
    }

    /** Credenciales SNMPv3 de los firewalls. Contraseña vacía = se conserva la guardada (nunca se muestran). */
    public function guardarSnmpv3(Request $request)
    {
        $request->validate([
            'snmpv3_usuario' => 'nullable|string|max:64',
            'snmpv3_nivel' => 'required|in:authPriv,authNoPriv,noAuthNoPriv',
            'snmpv3_auth_proto' => 'required|in:MD5,SHA,SHA256,SHA512',
            'snmpv3_priv_proto' => 'required|in:DES,AES,AES256',
            'snmpv3_auth_pass' => 'nullable|string|min:8|max:128',
            'snmpv3_priv_pass' => 'nullable|string|min:8|max:128',
        ], [
            'snmpv3_auth_pass.min' => 'La contraseña de autenticación SNMPv3 debe tener al menos 8 caracteres.',
            'snmpv3_priv_pass.min' => 'La contraseña de cifrado SNMPv3 debe tener al menos 8 caracteres.',
        ]);

        $cfg = ConfiguracionGeneral::firstOrFail();
        $datos = [
            'snmpv3_usuario' => trim((string) $request->input('snmpv3_usuario')) ?: null,
            'snmpv3_nivel' => $request->input('snmpv3_nivel'),
            'snmpv3_auth_proto' => $request->input('snmpv3_auth_proto'),
            'snmpv3_priv_proto' => $request->input('snmpv3_priv_proto'),
        ];
        foreach (['snmpv3_auth_pass', 'snmpv3_priv_pass'] as $campo) {
            if ($request->filled($campo)) {
                $datos[$campo] = $request->input($campo);
            }
        }
        // Lo habitual: una sola contraseña para autenticación y cifrado
        if ($request->boolean('snmpv3_misma_contrasena') && $request->filled('snmpv3_auth_pass')) {
            $datos['snmpv3_priv_pass'] = $request->input('snmpv3_auth_pass');
        }
        if ($request->boolean('snmpv3_borrar') || !$datos['snmpv3_usuario']) {
            $datos = array_merge($datos, ['snmpv3_usuario' => null, 'snmpv3_auth_pass' => null, 'snmpv3_priv_pass' => null]);
        }

        $tieneAuth = array_key_exists('snmpv3_auth_pass', $datos) ? $datos['snmpv3_auth_pass'] !== null : $cfg->snmpv3_auth_pass !== null;
        $tienePriv = array_key_exists('snmpv3_priv_pass', $datos) ? $datos['snmpv3_priv_pass'] !== null : $cfg->snmpv3_priv_pass !== null;
        // noAuthNoPriv: solo el nombre de usuario, sin contraseñas (las guardadas se conservan por si se vuelve a un nivel con contraseña)
        $sinContrasena = $datos['snmpv3_nivel'] === 'noAuthNoPriv';
        if ($datos['snmpv3_usuario'] && !$sinContrasena && (!$tieneAuth || ($datos['snmpv3_nivel'] === 'authPriv' && !$tienePriv))) {
            return redirect()->route('settings.index', ['section' => 'general'])
                ->with('error', 'Faltan contraseñas SNMPv3: la de autenticación' . ($datos['snmpv3_nivel'] === 'authPriv' ? ' y la de cifrado (nivel authPriv)' : '') . '.');
        }

        $cfg->update($datos);
        return redirect()->route('settings.index', ['section' => 'general'])->with('success',
            $datos['snmpv3_usuario'] ? 'Credenciales SNMPv3 de firewalls guardadas. Los workers las usan desde su siguiente ciclo.' : 'Credenciales SNMPv3 eliminadas: los firewalls vuelven a usar comunidad v2c.');
    }

    /** Conexión de solo lectura al vCenter. Contraseña vacía = se conserva la guardada (nunca se muestra). */
    public function guardarVcenter(Request $request)
    {
        $request->validate([
            'vcenter_host' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-:]+$/'],
            'vcenter_usuario' => 'nullable|string|max:128',
            'vcenter_password' => 'nullable|string|max:255',
        ], ['vcenter_host.regex' => 'El host del vCenter debe ser una IP o un nombre (sin https://).']);

        $datos = [
            'vcenter_host' => trim((string) $request->input('vcenter_host')) ?: null,
            'vcenter_usuario' => trim((string) $request->input('vcenter_usuario')) ?: null,
        ];
        if ($request->filled('vcenter_password')) {
            $datos['vcenter_password'] = $request->input('vcenter_password');
        }
        if ($request->boolean('vcenter_borrar') || !$datos['vcenter_usuario']) {
            $datos = array_merge($datos, ['vcenter_usuario' => null, 'vcenter_password' => null]);
        }
        ConfiguracionGeneral::firstOrFail()->update($datos);
        \Illuminate\Support\Facades\Cache::forget('vcenter_resumen');

        return redirect()->route('settings.index', ['section' => 'general'])->with('success',
            $datos['vcenter_usuario'] ? 'Conexión al vCenter guardada. La sección Virtualización la usa desde ya.' : 'Conexión al vCenter eliminada.');
    }

    /** Credenciales de los hosts ESXi (las usa worker/esxi_collector.py). Contraseña vacía = se conserva la guardada. */
    public function guardarEsxi(Request $request)
    {
        $request->validate(['esxi_usuario' => 'nullable|string|max:128', 'esxi_password' => 'nullable|string|max:255',
            'esxi_datastores_ignorados' => 'nullable|string|max:2000']);
        $datos = ['esxi_usuario' => trim((string) $request->input('esxi_usuario')) ?: null];
        if ($request->filled('esxi_password')) {
            $datos['esxi_password'] = $request->input('esxi_password');
        }
        if ($request->boolean('esxi_borrar') || !$datos['esxi_usuario']) {
            $datos = ['esxi_usuario' => null, 'esxi_password' => null];
        }
        // Un nombre por línea, sin repetidos ni líneas vacías
        $ignorados = collect(preg_split('/[\r\n,]+/', (string) $request->input('esxi_datastores_ignorados')))
            ->map(fn ($n) => trim($n))->filter()->unique(fn ($n) => mb_strtolower($n))->implode("\n");
        $datos['esxi_datastores_ignorados'] = $ignorados ?: null;
        ConfiguracionGeneral::firstOrFail()->update($datos);

        return redirect()->route('settings.index', ['section' => 'general'])->with('success',
            $datos['esxi_usuario'] ? 'Credenciales de ESXi guardadas. El colector las usa en su siguiente lectura (1 min).' : 'Credenciales de ESXi eliminadas.');
    }

    /** Lee un host con lo escrito (o lo guardado). Las credenciales viajan por variables de entorno, no por la línea de comandos. */
    public function probarEsxi(Request $request)
    {
        $request->validate(['ip' => 'required|ipv4', 'usuario' => 'nullable|string|max:128', 'contrasena' => 'nullable|string|max:255']);
        $entorno = array_filter(['NM_ESXI_USUARIO' => $request->input('usuario'), 'NM_ESXI_CLAVE' => $request->input('contrasena')]);
        $proceso = new \Symfony\Component\Process\Process([\App\Support\Python::binario(), base_path('worker/esxi_collector.py'), '--probar', $request->input('ip')],
            base_path(), \App\Support\Python::entorno($entorno));
        $proceso->setTimeout(45);
        try {
            $proceso->run();
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'mensaje' => 'Tiempo de espera agotado.'], 422);
        }
        $lineas = array_filter(array_map('trim', explode("\n", $proceso->getOutput())));
        $r = json_decode((string) end($lineas), true);
        if (!is_array($r)) {
            return response()->json(['ok' => false, 'mensaje' => 'Salida inválida: ' . \Illuminate\Support\Str::limit(trim($proceso->getErrorOutput() ?: $proceso->getOutput()), 200)], 422);
        }
        return response()->json($r, $r['ok'] ? 200 : 422);
    }

    /** Prueba la conexión con lo escrito en el formulario (o lo guardado si va vacío). */
    public function probarVcenter(Request $request, \App\Services\VcenterService $vcenter)
    {
        $request->validate(['host' => 'nullable|string|max:255', 'usuario' => 'nullable|string|max:128', 'contrasena' => 'nullable|string|max:255']);
        $r = $vcenter->probar($request->input('host'), $request->input('usuario'), $request->input('contrasena'));
        return response()->json($r, $r['ok'] ? 200 : 422);
    }

    /** Prueba las credenciales SNMPv3 guardadas contra un firewall (sysName/sysDescr). */
    public function probarSnmpv3(Request $request)
    {
        $request->validate(['ip' => 'required|ipv4']);
        return $this->ejecutarSnmpCheck(['--v3-firewall', $request->input('ip')]);
    }

    /**
     * Con solo usuario y contraseña, prueba las combinaciones habituales de protocolo contra un firewall.
     * Si la contraseña viene vacía se usa la guardada. Las credenciales viajan por variables de entorno.
     */
    public function detectarSnmpv3(Request $request)
    {
        $request->validate(['ip' => 'required|ipv4', 'usuario' => 'required|string|max:64', 'contrasena' => 'nullable|string|max:128']);
        // Sin contraseña (ni escrita ni guardada) solo se prueba el nivel noAuthNoPriv
        $clave = $request->input('contrasena') ?: (string) ConfiguracionGeneral::firstOrFail()->snmpv3_auth_pass;
        return $this->ejecutarSnmpCheck(['--v3-detectar', $request->input('ip')], 60,
            ['NM_V3_USUARIO' => $request->input('usuario'), 'NM_V3_CONTRASENA' => $clave]);
    }

    private function ejecutarSnmpCheck(array $argumentos, int $limite = 20, array $entorno = [])
    {
        $proceso = new \Symfony\Component\Process\Process([\App\Support\Python::binario(), base_path('worker/snmp_check.py'), ...$argumentos],
            base_path(), \App\Support\Python::entorno($entorno));
        $proceso->setTimeout($limite);
        try {
            $proceso->run();
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'mensaje' => 'Tiempo de espera agotado.'], 422);
        }
        $lineas = array_filter(array_map('trim', explode("\n", $proceso->getOutput())));
        $r = json_decode((string) end($lineas), true);
        if (!is_array($r)) {
            return response()->json(['ok' => false, 'mensaje' => 'Salida inválida: ' . \Illuminate\Support\Str::limit(trim($proceso->getErrorOutput() ?: $proceso->getOutput()), 200)], 422);
        }
        return response()->json($r, $r['ok'] ? 200 : 422);
    }

    public function guardarTelegram(Request $request)
    {
        $request->validate([
            'telegram_bot_token' => ['nullable', 'string', 'max:100', 'regex:/^\d{5,}:[A-Za-z0-9_-]{30,}$/'],
            'telegram_chat_id' => ['nullable', 'string', 'max:64', 'regex:/^(-?\d+|@[A-Za-z0-9_]{5,})$/'],
            'telegram_chat_id_servidores' => ['nullable', 'string', 'max:64', 'regex:/^(-?\d+|@[A-Za-z0-9_]{5,})$/'],
            'telegram_severidad_min' => 'required|in:critica,advertencia',
        ], [
            'telegram_bot_token.regex' => 'El token no tiene el formato de BotFather (números:letras).',
            'telegram_chat_id.regex' => 'El chat ID debe ser un número (p. ej. -1001234567890) o @nombre_del_canal.',
            'telegram_chat_id_servidores.regex' => 'El chat de servidores debe ser un número (p. ej. -1001234567890) o @nombre_del_canal.',
        ]);

        $cfg = ConfiguracionGeneral::firstOrFail();
        $datos = [
            'telegram_chat_id' => trim((string) $request->input('telegram_chat_id')) ?: null,
            'telegram_chat_id_servidores' => trim((string) $request->input('telegram_chat_id_servidores')) ?: null,
            'telegram_severidad_min' => $request->input('telegram_severidad_min'),
            'telegram_resueltas' => $request->boolean('telegram_resueltas'),
            'telegram_imagenes' => $request->boolean('telegram_imagenes'),
            'telegram_activo' => $request->boolean('telegram_activo'),
        ];
        // Token vacío = se conserva el guardado (nunca se muestra en la página)
        if ($request->boolean('telegram_borrar_token')) {
            $datos['telegram_bot_token'] = null;
        } elseif ($request->filled('telegram_bot_token')) {
            $datos['telegram_bot_token'] = trim($request->input('telegram_bot_token'));
        }

        $tieneToken = array_key_exists('telegram_bot_token', $datos) ? $datos['telegram_bot_token'] !== null : $cfg->telegram_bot_token !== null;
        if ($datos['telegram_activo'] && (!$tieneToken || !$datos['telegram_chat_id'])) {
            return redirect()->route('settings.index', ['section' => 'notificaciones'])
                ->withInput($request->except('telegram_bot_token'))
                ->with('error', 'Para activar las alertas de Telegram se necesitan el token del bot y el chat ID.');
        }

        $cfg->update($datos);
        return redirect()->route('settings.index', ['section' => 'notificaciones'])
            ->with('success', $datos['telegram_activo'] ? 'Alertas de Telegram activadas.' : 'Configuración de Telegram guardada (alertas desactivadas).');
    }

    /** Chats que escribieron al bot en las últimas 24 h, para elegir el chat ID. Usa el token escrito o el guardado. */
    public function chatsTelegram(Request $request)
    {
        $cfg = ConfiguracionGeneral::firstOrFail();
        $token = trim((string) $request->input('token')) ?: (string) $cfg->telegram_bot_token;
        if ($token === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Escribe primero el token del bot.'], 422);
        }
        try {
            $bot = new TelegramBot($token);
            $yo = $bot->yo();
            return response()->json(['ok' => true, 'bot' => '@' . ($yo['username'] ?? ''), 'chats' => $bot->chatsRecientes()]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function probarTelegram(AlertasTelegram $alertas)
    {
        $cfg = ConfiguracionGeneral::firstOrFail();
        $bot = TelegramBot::desdeConfiguracion($cfg);
        if (!$bot || !$cfg->telegram_chat_id) {
            return response()->json(['ok' => false, 'mensaje' => 'Guarda primero el token del bot y el chat ID.'], 422);
        }
        try {
            $r = $alertas->probar($cfg, $bot);
            Cache::forget('telegram_ultimo_error');
            return response()->json(['ok' => true, 'mensaje' => 'Mensaje de prueba enviado' . ($r['chats'] > 1 ? " a los {$r['chats']} grupos" : '')
                . ($r['imagen'] ? ' con gráfica de ejemplo.' : '.')]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|in:Admin,Operador,admin,operator,Auditor',
        ]);

        $newRole = ucfirst(strtolower($request->role));
        if ($newRole == 'Operator') $newRole = 'Operador';

        // Evitar que el admin activo se quite su propio rol por accidente si es el unico
        if ($user->id === auth()->id() && $newRole !== 'Admin') {
            return redirect()->route('settings.index', ['section' => 'cuentas'])->with('error', 'No puedes quitarte el rol de Administrador a ti mismo.');
        }

        $user->update(['role' => $newRole]);

        return redirect()->route('settings.index', ['section' => 'cuentas'])->with('success', 'Rol de "' . $user->name . '" actualizado a ' . $newRole . '.');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'prefijo' => 'nullable|string|in:Ing.,Lic.,Tec.,TSU.,Dr.,Mtro.,Mtra.,C.P.,Arq.',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => 'required|in:Admin,Operador,Auditor',
        ]);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        User::create([
            'name' => $request->name,
            'prefijo' => $request->prefijo,
            'avatar' => $avatarPath,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return redirect()->route('settings.index', ['section' => 'cuentas'])->with('success', 'Cuenta de "' . $request->name . '" creada exitosamente.');
    }

    public function deleteUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('settings.index', ['section' => 'cuentas'])->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        // Delete avatar file if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('settings.index', ['section' => 'cuentas'])->with('success', 'Cuenta de "' . $name . '" eliminada.');
    }

    public function updateUser(Request $request, User $user)
    {
        if ($user->id === auth()->id() && $request->role !== 'Admin') {
            return redirect()->route('settings.index', ['section' => 'cuentas'])->with('error', 'No puedes quitarte el rol de Administrador a ti mismo.');
        }

        $rules = [
            'name' => 'required|string|max:255',
            'prefijo' => 'nullable|string|in:Ing.,Lic.,Tec.,TSU.,Dr.,Mtro.,Mtra.,C.P.,Arq.',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:Admin,Operador,Auditor',
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Password::min(8)];
        }

        $request->validate($rules);

        $data = [
            'name' => $request->name,
            'prefijo' => $request->prefijo,
            'email' => $request->email,
            'role' => $request->role,
        ];

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        // Handle remove avatar checkbox
        if ($request->has('remove_avatar') && !$request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = null;
        }

        // Only update password if provided
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('settings.index', ['section' => 'cuentas'])->with('success', 'Cuenta de "' . $request->name . '" actualizada exitosamente.');
    }
}
