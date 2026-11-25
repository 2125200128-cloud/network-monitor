<?php

namespace App\Services;

use App\Models\AlarmaRed;
use App\Models\ConfiguracionGeneral;
use App\Support\RedesPrioritarias;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Alertas por correo (SMTP), con los mismos dos grupos que Telegram:
 *  - Destinatarios de redes: switches, firewalls, routers, APs y recaudadoras. Si no hay lista de servidores, reciben todo.
 *  - Destinatarios de servidores: servidores, virtualización y sitios web.
 * En cada revisión de noc:sync se manda como mucho UN correo por grupo con lo nuevo, lo resuelto y los recordatorios de
 * la red prioritaria, para no llenar la bandeja. Hasta MAX_DETALLE alarmas nuevas van con todo su diagnóstico; más que
 * eso (una caída masiva) van en tabla.
 * Si el servidor de correo falla, lo que no salió se reintenta en las revisiones siguientes (durante VENTANA_MIN) y se
 * espera ESPERA_TRAS_ERROR_MIN antes de volver a conectarse, para no frenar cada minuto a noc:sync.
 * Nunca lanza excepciones desde notificar(): un fallo del correo no debe detener noc:sync.
 */
class AlertasCorreo
{
    private const MAX_DETALLE = 5;
    private const MAX_FILAS = 60;
    private const MINUTOS_INTERMITENTE = 15;
    private const VENTANA_MIN = 60;
    private const ESPERA_TRAS_ERROR_MIN = 5;
    /** Tipos de equipo cuyas alarmas no se avisan (igual que en Telegram; siguen en Alertas y en el dashboard). */
    private const TIPOS_SIN_CORREO = ['telefono'];

    public const SEGURIDAD = [
        'tls' => 'STARTTLS · puerto 587',
        'ssl' => 'SSL/TLS · puerto 465',
        'ninguna' => 'Sin cifrado · puerto 25',
    ];

    public const GRUPOS = ['redes' => 'Redes', 'servidores' => 'Servidores y sitios web', 'todo' => 'Redes y servidores'];

    /** Activado y con lo mínimo para enviar: servidor, remitente y al menos un destinatario de redes. */
    public static function listo(?ConfiguracionGeneral $cfg): bool
    {
        return $cfg && $cfg->correo_activo && $cfg->correo_host && $cfg->correo_remitente && self::direcciones($cfg->correo_destinos_redes);
    }

    /** Direcciones válidas de una lista escrita a mano (una por línea, o separadas por coma o punto y coma). */
    public static function direcciones(?string $texto): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $texto))
            ->map(fn ($c) => mb_strtolower(trim($c)))
            ->filter(fn ($c) => $c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL))
            ->unique()->values()->all();
    }

    /** Lo escrito que no es una dirección de correo (para avisarlo al guardar). */
    public static function invalidas(?string $texto): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $texto))->map(fn ($c) => trim($c))
            ->filter(fn ($c) => $c !== '' && !filter_var($c, FILTER_VALIDATE_EMAIL))->values()->all();
    }

    /**
     * Grupos de destinatarios y los sectores que recibe cada uno. Sin lista de servidores (o si es la misma), todo va a redes.
     * @return list<array{grupo: string, correos: string[], sectores: string[]}>
     */
    public static function destinos(ConfiguracionGeneral $cfg): array
    {
        $redes = self::direcciones($cfg->correo_destinos_redes);
        $servidores = self::direcciones($cfg->correo_destinos_servidores);
        $mismos = !$servidores || (count($servidores) === count($redes) && !array_diff($servidores, $redes));
        return $mismos
            ? [['grupo' => 'todo', 'correos' => $redes, 'sectores' => ['redes', 'servidores']]]
            : [['grupo' => 'redes', 'correos' => $redes, 'sectores' => ['redes']],
               ['grupo' => 'servidores', 'correos' => $servidores, 'sectores' => ['servidores']]];
    }

    /** Conexión SMTP con lo guardado en Ajustes (no usa el .env). */
    public static function mailer(ConfiguracionGeneral $cfg): Mailer
    {
        $seguridad = $cfg->correo_seguridad ?: 'tls';
        return Mail::build([
            'transport' => 'smtp',
            'scheme' => $seguridad === 'ssl' ? 'smtps' : 'smtp',
            'host' => $cfg->correo_host,
            'port' => (int) ($cfg->correo_puerto ?: 587),
            'username' => $cfg->correo_usuario ?: null,
            'password' => $cfg->correo_usuario ? (string) $cfg->correo_password : null,
            'timeout' => 15,
            // STARTTLS obligatorio en «tls» (no se manda la contraseña en claro si el servidor no lo ofrece)
            'auto_tls' => $seguridad !== 'ninguna',
            'require_tls' => $seguridad === 'tls',
            'verify_peer' => (bool) $cfg->correo_verificar_certificado,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ]);
    }

    /** @return array{enviados:int, errores:int} */
    public function notificar(Collection $resueltas): array
    {
        $res = ['enviados' => 0, 'errores' => 0];
        $cfg = ConfiguracionGeneral::first();
        if (!self::listo($cfg) || self::enPausa()) {
            return $res;
        }

        try {
            $severidades = $cfg->correo_severidad_min === 'advertencia' ? ['critica', 'advertencia'] : ['critica'];
            // Lo nuevo y lo que no salió en revisiones anteriores (servidor caído, o pospuesto por intermitente)
            $nuevas = AlarmaRed::with('dispositivo')->where('estado', '!=', 'resuelta')->whereNull('notificada_correo_at')
                ->whereIn('severidad', $severidades)->where('created_at', '>=', now()->subMinutes(self::VENTANA_MIN))->get()
                ->reject(fn (AlarmaRed $a) => $this->excluida($a))
                ->reject(fn (AlarmaRed $a) => $a->created_at->gt(now()->subMinutes(self::MINUTOS_INTERMITENTE)) && $this->esIntermitente($a))
                ->sortBy(fn (AlarmaRed $a) => [$a->esPrioritaria() ? 0 : 1, $a->severidad === 'critica' ? 0 : 1, $a->fecha_inicio?->timestamp ?? 0])
                ->values();
            $resueltas = $cfg->correo_resueltas
                ? $resueltas->filter(fn (AlarmaRed $a) => $a->notificada_correo_at !== null)->reject(fn (AlarmaRed $a) => $this->excluida($a))->values()
                : collect();
            $recordar = $this->porRecordar($cfg, $severidades);
        } catch (\Throwable $e) {
            Log::warning('Alertas por correo: ' . $e->getMessage());
            return $res;
        }

        $mailer = null;
        foreach (self::destinos($cfg) as $d) {
            $deEste = fn (AlarmaRed $a) => in_array(AlertasTelegram::sector($a), $d['sectores'], true);
            $n = $nuevas->filter($deEste)->values();
            $r = $resueltas->filter($deEste)->values();
            $k = $recordar->filter($deEste)->values();
            if ($n->isEmpty() && $r->isEmpty() && $k->isEmpty()) {
                continue;
            }
            try {
                $mailer ??= self::mailer($cfg);
                $this->enviar($mailer, $cfg, $d['correos'], $this->asunto($d['grupo'], $n, $r, $k), [
                    'grupo' => self::GRUPOS[$d['grupo']],
                    'nuevas' => $n->take(self::MAX_FILAS)->map(fn (AlarmaRed $a) => $this->fila($a))->all(),
                    'nuevasTotal' => $n->count(),
                    'detalle' => $n->count() <= self::MAX_DETALLE,
                    'recordar' => $k->take(self::MAX_FILAS)->map(fn (AlarmaRed $a) => $this->fila($a))->all(),
                    'recordarTotal' => $k->count(),
                    'resueltas' => $r->take(self::MAX_FILAS)->map(fn (AlarmaRed $a) => $this->fila($a))->all(),
                    'resueltasTotal' => $r->count(),
                ]);
                if ($n->isNotEmpty()) {
                    AlarmaRed::whereIn('id', $n->pluck('id'))->update(['notificada_correo_at' => now()]);
                }
                if ($k->isNotEmpty()) {
                    AlarmaRed::whereIn('id', $k->pluck('id'))->update(['recordada_correo_at' => now()]);
                }
                $res['enviados']++;
            } catch (\Throwable $e) {
                $res['errores']++;
                self::registrarError($e);
                break;   // el servidor no responde: el otro grupo también fallaría (y esperaría otra vez el tiempo límite)
            }
        }
        if ($res['enviados'] && !$res['errores']) {
            Cache::forget('correo_ultimo_error');
        }
        return $res;
    }

    /**
     * Aviso agrupado del módulo de recaudadoras (recaudadoras:revisar), al grupo de redes.
     * @param list<array{titulo: string, color: string, nota?: string, lineas: string[]}> $secciones
     */
    /** @param string|null $imagen PNG con el mapa y la ficha de las recaudadoras afectadas (va dentro del correo) */
    public function avisoRecaudadoras(string $asunto, array $secciones, ?string $imagen = null): void
    {
        $cfg = ConfiguracionGeneral::first();
        if (!self::listo($cfg) || self::enPausa()) {
            return;
        }
        $d = self::destinos($cfg)[0];   // el de redes (o el único)
        try {
            $this->enviar(self::mailer($cfg), $cfg, $d['correos'], '[NOC · Recaudadoras] ' . $asunto,
                ['grupo' => self::GRUPOS[$d['grupo']], 'secciones' => $secciones, 'enlaceModulo' => $this->enlace('recaudadoras'), 'imagen' => $imagen]);
            Cache::forget('correo_ultimo_error');
        } catch (\Throwable $e) {
            self::registrarError($e);
        }
    }

    /** Correo de prueba a cada grupo con lo que va a recibir. Lanza la excepción para mostrarla en Ajustes. */
    public function probar(ConfiguracionGeneral $cfg): array
    {
        $mailer = self::mailer($cfg);
        $destinos = self::destinos($cfg);
        foreach ($destinos as $d) {
            $recibe = match ($d['grupo']) {
                'todo' => 'Todas las alarmas: redes (switches, firewalls, routers, APs, recaudadoras), servidores, virtualización y sitios web.',
                'redes' => 'Las alarmas de redes: switches, firewalls, routers, APs y recaudadoras.',
                default => 'Las alarmas de servidores, virtualización (ESXi, VMs, datastores) y sitios web.',
            };
            $lineas = [$recibe,
                'Se envían: ' . ($cfg->correo_severidad_min === 'advertencia' ? 'críticas y advertencias' : 'solo críticas')
                    . ($cfg->correo_resueltas ? ', y avisos de resolución.' : '.'),
                'Como mucho un correo por revisión (cada minuto) con todo lo nuevo junto.'];
            if ((int) $cfg->correo_recordatorio_min > 0 && RedesPrioritarias::texto() !== '') {
                $lineas[] = 'Las fallas de la red prioritaria (' . RedesPrioritarias::texto() . ') se recuerdan cada '
                    . (int) $cfg->correo_recordatorio_min . ' minutos mientras sigan activas.';
            }
            $lineas[] = 'Destinatarios de este grupo: ' . implode(', ', $d['correos']) . '.';
            $this->enviar($mailer, $cfg, $d['correos'], '[NOC] Prueba de alertas por correo · ' . self::GRUPOS[$d['grupo']], [
                'grupo' => self::GRUPOS[$d['grupo']],
                'secciones' => [['titulo' => 'Las alertas por correo están configuradas correctamente', 'color' => '#059669', 'lineas' => $lineas]],
            ]);
        }
        Cache::forget('correo_ultimo_error');
        Cache::forget('correo_pausa_hasta');
        return ['grupos' => count($destinos), 'correos' => array_sum(array_map(fn ($d) => count($d['correos']), $destinos))];
    }

    private function enviar(Mailer $mailer, ConfiguracionGeneral $cfg, array $correos, string $asunto, array $datos): void
    {
        $mailer->send('correo.alertas', $datos + ['asunto' => $asunto, 'enlaceNoc' => $this->enlace('alertas')],
            function ($m) use ($cfg, $correos, $asunto) {
                $m->from($cfg->correo_remitente, $cfg->correo_remitente_nombre ?: 'NOC Network Monitor')->to($correos)->subject($asunto);
            });
        Log::info('Alerta por correo enviada', ['asunto' => $asunto, 'destinatarios' => count($correos)]);
    }

    public static function enPausa(): bool
    {
        $hasta = Cache::get('correo_pausa_hasta');
        return $hasta && now()->lt(\Carbon\Carbon::parse($hasta));
    }

    /**
     * El error del servidor con una explicación de qué hacer. Los de Microsoft 365 (Exchange Online) traen un código
     * 5.7.x que solo dice qué se rechazó: aquí se traduce a la causa habitual.
     */
    public static function explicar(\Throwable $e): string
    {
        $original = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $e->getMessage())), 300);
        $causa = match (true) {
            str_contains($original, '5.7.139') => 'Microsoft 365 no permite enviar con usuario y contraseña (SMTP AUTH) para esta cuenta: '
                . 'el administrador de Microsoft 365 debe activar «SMTP autenticado» en el buzón y que la cuenta no tenga MFA ni la bloqueen los «valores predeterminados de seguridad».',
            str_contains($original, '5.7.3') || str_contains($original, '535') => 'Usuario o contraseña incorrectos (o la contraseña cambió).',
            str_contains($original, '5.7.60') || stripos($original, 'SendAsDenied') !== false => 'El remitente no coincide con la cuenta: en Microsoft 365 el remitente debe ser la misma dirección del usuario (o la cuenta necesita permiso «Enviar como»).',
            str_contains($original, '5.7.57') => 'El servidor exige iniciar sesión: escribe el usuario y la contraseña de la cuenta.',
            str_contains($original, '5.7.708') || str_contains($original, '5.7.750') => 'Microsoft 365 bloqueó el envío desde esta cuenta (reputación o cuenta no verificada): revísalo con el administrador.',
            stripos($original, 'Connection could not be established') !== false || stripos($original, 'timed out') !== false || stripos($original, 'Connection refused') !== false
                => 'No hay conexión con el servidor de correo: revisa el nombre y el puerto, y que el firewall deje salir al servidor del NOC por ese puerto.',
            stripos($original, 'certificate') !== false || stripos($original, 'SSL') !== false
                => 'Falló la verificación del certificado del servidor de correo: si es un servidor interno con certificado propio, desmarca «Verificar certificado».',
            default => null,
        };
        return $causa ? "{$causa} (Detalle: {$original})" : $original;
    }

    private static function registrarError(\Throwable $e): void
    {
        $mensaje = self::explicar($e);
        Cache::put('correo_ultimo_error', ['mensaje' => $mensaje, 'fecha' => now()->toDateTimeString()], now()->addDay());
        Cache::put('correo_pausa_hasta', now()->addMinutes(self::ESPERA_TRAS_ERROR_MIN)->toDateTimeString(), now()->addMinutes(self::ESPERA_TRAS_ERROR_MIN));
        Log::warning('Alertas por correo: ' . $mensaje);
    }

    /** Fallas de la red prioritaria activas a las que ya les toca recordatorio por correo (misma regla que Telegram). */
    private function porRecordar(ConfiguracionGeneral $cfg, array $severidades): Collection
    {
        $minutos = (int) $cfg->correo_recordatorio_min;
        if ($minutos <= 0) {
            return collect();
        }
        $limite = now()->subMinutes($minutos);
        return AlarmaRed::with('dispositivo')->where('estado', '!=', 'resuelta')->whereIn('severidad', $severidades)->get()
            ->filter(function (AlarmaRed $a) use ($limite) {
                // Desde cuándo cuenta: el último recordatorio, el aviso inicial o (recaudadoras, que avisa su módulo) su registro
                $base = $a->recordada_correo_at ?? $a->notificada_correo_at ?? ($a->categoria === 'recaudadora' ? $a->created_at : null);
                return $base && $base->lte($limite);
            })
            ->reject(fn (AlarmaRed $a) => $a->dispositivo && in_array($a->dispositivo->tipo_dispositivo, self::TIPOS_SIN_CORREO, true))
            ->filter(fn (AlarmaRed $a) => $a->esPrioritaria())
            ->sortBy(fn (AlarmaRed $a) => [$a->severidad === 'critica' ? 0 : 1, $a->fecha_inicio?->timestamp ?? 0])
            ->values();
    }

    /** Las de recaudadoras las avisa su propio módulo (mensaje agrupado); los teléfonos IP no se avisan. */
    private function excluida(AlarmaRed $a): bool
    {
        return $a->categoria === 'recaudadora'
            || ($a->dispositivo && in_array($a->dispositivo->tipo_dispositivo, self::TIPOS_SIN_CORREO, true));
    }

    /** La misma alarma ya se avisó por correo y se resolvió poco antes de que esta apareciera (equipo intermitente). */
    private function esIntermitente(AlarmaRed $a): bool
    {
        $creada = $a->created_at ?? now();
        return AlarmaRed::where('clave', $a->clave)->where('id', '!=', $a->id)
            ->where('estado', 'resuelta')->whereNotNull('notificada_correo_at')
            ->whereBetween('fecha_resolucion', [$creada->copy()->subMinutes(self::MINUTOS_INTERMITENTE), $creada])
            ->exists();
    }

    private function asunto(string $grupo, Collection $nuevas, Collection $resueltas, Collection $recordar): string
    {
        $prefijo = '[NOC' . ($grupo === 'todo' ? '' : ' · ' . ($grupo === 'redes' ? 'Redes' : 'Servidores')) . '] ';
        if ($nuevas->count() === 1) {
            $a = $nuevas->first();
            return $prefijo . ($a->esPrioritaria() ? 'RED PRIORITARIA · ' : '') . ($a->severidad === 'critica' ? 'CRÍTICA: ' : 'Advertencia: ')
                . $a->titulo . $this->deQueEquipo($a);
        }
        if ($nuevas->count() > 1) {
            $criticas = $nuevas->where('severidad', 'critica')->count();
            return $prefijo . $nuevas->count() . ' alarmas nuevas' . ($criticas ? " ({$criticas} críticas)" : '');
        }
        if ($recordar->isNotEmpty()) {
            return $prefijo . 'Recordatorio red prioritaria: ' . ($recordar->count() === 1 ? '1 falla sigue activa' : $recordar->count() . ' fallas siguen activas');
        }
        return $prefijo . ($resueltas->count() === 1 ? 'Resuelta: ' . $resueltas->first()->titulo . $this->deQueEquipo($resueltas->first()) : $resueltas->count() . ' alarmas resueltas');
    }

    /** Datos de una alarma para la plantilla del correo. */
    private function fila(AlarmaRed $a): array
    {
        $d = $a->dispositivo;
        $ubicacion = $d && !preg_match('/descubierto|alta manual/i', (string) $d->ubicacion) ? $d->ubicacion : null;
        $fin = $a->fecha_resolucion ?? now();
        // «nombre (IP)» sin repetir lo que el título ya dice
        $ip = $a->ipDe();
        $ipAparte = $ip && !str_contains((string) $a->titulo, $ip) ? $ip : null;
        $equipo = $d && !str_contains((string) $a->titulo, (string) $d->nombre)
            ? $d->nombre . ($ipAparte ? " ({$ipAparte})" : '')
            : $ipAparte;
        return [
            'equipo_txt' => $equipo,
            'critica' => $a->severidad === 'critica',
            'prioritaria' => $a->esPrioritaria(),
            'titulo' => $a->titulo,
            'equipo' => $d?->nombre,
            'ip' => $a->ipDe(),
            'ubicacion' => $ubicacion,
            'mensaje' => $a->mensaje,
            'causa' => $a->causa_raiz,
            'impacto' => $a->impacto,
            'accion' => $a->accion_sugerida,
            'inicio' => $a->fecha_inicio?->format('d/m/Y H:i'),
            'duracion' => $a->fecha_inicio ? self::duracion((int) $a->fecha_inicio->diffInSeconds($fin)) : null,
            'enlace' => $a->dispositivo_id ? $this->enlace("dispositivos/{$a->dispositivo_id}") : null,
        ];
    }

    private function deQueEquipo(AlarmaRed $a): string
    {
        $d = $a->dispositivo;
        return $d && !str_contains((string) $a->titulo, (string) $d->nombre) ? ' · ' . $d->nombre : '';
    }

    public static function duracion(int $s): string
    {
        return match (true) {
            $s < 60 => "{$s} s",
            $s < 3600 => intdiv($s, 60) . ' min',
            $s < 86400 => intdiv($s, 3600) . ' h ' . intdiv($s % 3600, 60) . ' min',
            default => intdiv($s, 86400) . ' d ' . intdiv($s % 86400, 3600) . ' h',
        };
    }

    /** Enlace al NOC solo si APP_URL es accesible desde otras PCs (no localhost). */
    private function enlace(string $ruta): ?string
    {
        $base = rtrim((string) config('app.url'), '/');
        return $base === '' || preg_match('#//(localhost|127\.)#', $base) ? null : "{$base}/{$ruta}";
    }
}
