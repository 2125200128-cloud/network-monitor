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
            'ip_switch_core' => env('IP_SWITCH_CORE', '10.4.254.3'),
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

        return view('settings.index', compact('settings', 'users', 'section', 'retencion', 'telegram'));
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
            'comunidad_snmp_firewall' => 'nullable|string|max:255',
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
            // Vacía = los firewalls siguen usando su comunidad individual
            $settings->update(['comunidad_snmp_firewall' => trim((string) $request->input('comunidad_snmp_firewall')) ?: null] + $request->all());
            // La próxima ejecución de noc:sync aplica la retención nueva sin esperar la hora
            Cache::forget('noc_retencion_hecha');
        }

        return redirect()->route('settings.index', ['section' => 'general'])->with('success', 'Configuraciones guardadas exitosamente.');
    }

    public function guardarTelegram(Request $request)
    {
        $request->validate([
            'telegram_bot_token' => ['nullable', 'string', 'max:100', 'regex:/^\d{5,}:[A-Za-z0-9_-]{30,}$/'],
            'telegram_chat_id' => ['nullable', 'string', 'max:64', 'regex:/^(-?\d+|@[A-Za-z0-9_]{5,})$/'],
            'telegram_severidad_min' => 'required|in:critica,advertencia',
        ], [
            'telegram_bot_token.regex' => 'El token no tiene el formato de BotFather (números:letras).',
            'telegram_chat_id.regex' => 'El chat ID debe ser un número (p. ej. -1001234567890) o @nombre_del_canal.',
        ]);

        $cfg = ConfiguracionGeneral::firstOrFail();
        $datos = [
            'telegram_chat_id' => trim((string) $request->input('telegram_chat_id')) ?: null,
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
            return response()->json(['ok' => true, 'mensaje' => 'Mensaje de prueba enviado' . ($r['imagen'] ? ' con gráfica de ejemplo.' : '.')]);
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
