<?php

namespace App\Services;

use App\Models\AlarmaRed;
use App\Models\ConfiguracionGeneral;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Envía a Telegram las alarmas nuevas y resueltas que registra noc:sync.
 *  - Hasta MAX_INDIVIDUALES alarmas nuevas: un mensaje por alarma (con gráfica del equipo si se pidió).
 *  - Más que eso (p.ej. una caída masiva): un solo resumen, para no saturar el chat.
 *  - Una alarma que reaparece poco después de resolverse (equipo intermitente) solo se vuelve a avisar
 *    si sigue activa pasados MINUTOS_INTERMITENTE.
 *  - Solo se avisa la resolución de alarmas cuyo inicio sí se notificó.
 * Nunca lanza excepciones: un fallo de Telegram no debe detener noc:sync.
 */
class AlertasTelegram
{
    private const MAX_INDIVIDUALES = 5;
    private const MAX_IMAGENES = 3;
    private const MAX_LINEAS_RESUMEN = 25;
    private const MINUTOS_INTERMITENTE = 15;

    public function __construct(private GraficaEquipo $grafica)
    {
    }

    /** @return array{enviados:int, errores:int} */
    public function notificar(Collection $nuevas, Collection $resueltas): array
    {
        $cfg = ConfiguracionGeneral::first();
        $bot = $cfg && $cfg->telegram_activo && $cfg->telegram_chat_id ? TelegramBot::desdeConfiguracion($cfg) : null;
        $res = ['enviados' => 0, 'errores' => 0];
        if (!$bot) {
            return $res;
        }

        $severidades = $cfg->telegram_severidad_min === 'advertencia' ? ['critica', 'advertencia'] : ['critica'];
        // Las que se pospusieron por intermitentes se avisan si siguen activas pasado el margen
        $pospuestas = AlarmaRed::where('estado', '!=', 'resuelta')->whereNull('notificada_telegram_at')
            ->whereBetween('created_at', [now()->subDay(), now()->subMinutes(self::MINUTOS_INTERMITENTE)])
            ->get()->filter(fn (AlarmaRed $a) => $this->esIntermitente($a));
        $nuevas = $nuevas->reject(fn (AlarmaRed $a) => $this->esIntermitente($a))->merge($pospuestas)
            ->filter(fn (AlarmaRed $a) => in_array($a->severidad, $severidades, true))
            ->sortBy(fn (AlarmaRed $a) => $a->severidad === 'critica' ? 0 : 1)->values();
        $resueltas = $cfg->telegram_resueltas
            ? $resueltas->filter(fn (AlarmaRed $a) => $a->notificada_telegram_at !== null)->values()
            : collect();

        $enviar = function (callable $fn) use (&$res) {
            try {
                $fn();
                $res['enviados']++;
                return true;
            } catch (\Throwable $e) {
                $res['errores']++;
                Cache::put('telegram_ultimo_error', ['mensaje' => $e->getMessage(), 'fecha' => now()->toDateTimeString()], now()->addDay());
                Log::warning('Alertas Telegram: ' . $e->getMessage());
                return false;
            }
        };
        $chat = (string) $cfg->telegram_chat_id;

        if ($nuevas->count() > self::MAX_INDIVIDUALES) {
            if ($enviar(fn () => $bot->enviarMensaje($chat, $this->resumenNuevas($nuevas)))) {
                $this->marcarNotificadas($nuevas);
            }
        } else {
            $imagenes = 0;
            foreach ($nuevas as $a) {
                $png = null;
                if ($cfg->telegram_imagenes && $a->dispositivo_id && $imagenes < self::MAX_IMAGENES) {
                    try {
                        $png = $this->grafica->png($a->dispositivo_id, $a->fecha_inicio);
                    } catch (\Throwable $e) {
                        Log::warning('Alertas Telegram: no se pudo generar la gráfica: ' . $e->getMessage());
                    }
                }
                $texto = $this->mensajeAlarma($a);
                $ok = $png
                    ? $enviar(function () use ($bot, $chat, $png, $texto) {
                        // El pie de foto admite 1024 caracteres: si no cabe, la foto va con el título y el detalle aparte
                        if (mb_strlen($texto) <= TelegramBot::MAX_PIE_FOTO) {
                            $bot->enviarFoto($chat, $png, $texto);
                        } else {
                            $bot->enviarFoto($chat, $png, strtok($texto, "\n"));
                            $bot->enviarMensaje($chat, $texto);
                        }
                    })
                    : $enviar(fn () => $bot->enviarMensaje($chat, $texto));
                $imagenes += $png ? 1 : 0;
                if ($ok) {
                    $this->marcarNotificadas(collect([$a]));
                }
            }
        }

        if ($resueltas->isNotEmpty()) {
            $enviar(fn () => $bot->enviarMensaje($chat, $this->mensajeResueltas($resueltas)));
        }

        if ($res['enviados'] && !$res['errores']) {
            Cache::forget('telegram_ultimo_error');
        }
        return $res;
    }

    /** Mensaje de prueba con una gráfica real (del switch core o del primer equipo con métricas). */
    public function probar(ConfiguracionGeneral $cfg, TelegramBot $bot): array
    {
        $chat = (string) $cfg->telegram_chat_id;
        $bot->enviarMensaje($chat, "✅ <b>NOC Network Monitor</b>\nLas alertas de Telegram están configuradas correctamente.\n"
            . 'Se enviarán: ' . ($cfg->telegram_severidad_min === 'advertencia' ? 'críticas y advertencias' : 'solo críticas')
            . ($cfg->telegram_resueltas ? ', y avisos de resolución.' : '.'));

        $conImagen = false;
        if ($cfg->telegram_imagenes) {
            $id = \Illuminate\Support\Facades\DB::table('dispositivos')->where('ip', $cfg->ip_switch_core)->value('id')
                ?? \Illuminate\Support\Facades\DB::table('metricas_red')->where('fecha_registro', '>=', now()->subMinutes(10))->value('dispositivo_id');
            $png = $id ? $this->grafica->png((int) $id) : null;
            if ($png) {
                $bot->enviarFoto($chat, $png, 'Ejemplo de la gráfica que acompaña a cada alerta.');
                $conImagen = true;
            }
        }
        return ['imagen' => $conImagen];
    }

    /** La misma alarma ya se avisó y se resolvió poco antes de que esta apareciera (equipo intermitente). */
    private function esIntermitente(AlarmaRed $a): bool
    {
        $creada = $a->created_at ?? now();
        return AlarmaRed::where('clave', $a->clave)->where('id', '!=', $a->id)
            ->where('estado', 'resuelta')->whereNotNull('notificada_telegram_at')
            ->whereBetween('fecha_resolucion', [$creada->copy()->subMinutes(self::MINUTOS_INTERMITENTE), $creada])
            ->exists();
    }

    private function marcarNotificadas(Collection $alarmas): void
    {
        AlarmaRed::whereIn('id', $alarmas->pluck('id'))->update(['notificada_telegram_at' => now()]);
    }

    private function mensajeAlarma(AlarmaRed $a): string
    {
        $e = fn ($t) => htmlspecialchars((string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $disp = $a->dispositivo;
        $lineas = [
            $this->icono($a->severidad) . ' <b>' . ($a->severidad === 'critica' ? 'CRÍTICA' : 'ADVERTENCIA') . '</b> · ' . $e($a->titulo),
        ];
        if ($disp) {
            $lineas[] = '<b>Equipo:</b> ' . $e($disp->nombre) . ($disp->ip ? ' (<code>' . $e($disp->ip) . '</code>)' : '');
        }
        if ($a->mensaje) {
            $lineas[] = $e($a->mensaje);
        }
        if ($a->causa_raiz) {
            $lineas[] = '<b>Causa probable:</b> ' . $e($a->causa_raiz);
        }
        if ($a->impacto) {
            $lineas[] = '<b>Impacto:</b> ' . $e($a->impacto);
        }
        if ($a->accion_sugerida) {
            $lineas[] = '<b>Acción sugerida:</b> ' . $e($a->accion_sugerida);
        }
        $lineas[] = '🕒 ' . $a->fecha_inicio?->format('d/m/Y H:i:s');
        if ($enlace = $this->enlace($a->dispositivo_id)) {
            $lineas[] = '<a href="' . $e($enlace) . '">Ver equipo en el NOC</a>';
        }
        return implode("\n", $lineas);
    }

    private function resumenNuevas(Collection $alarmas): string
    {
        $e = fn ($t) => htmlspecialchars((string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $criticas = $alarmas->where('severidad', 'critica')->count();
        $lineas = ['🚨 <b>' . $alarmas->count() . ' alarmas nuevas</b>' . ($criticas ? " ({$criticas} críticas)" : '')];
        foreach ($alarmas->take(self::MAX_LINEAS_RESUMEN) as $a) {
            $lineas[] = $this->icono($a->severidad) . ' ' . $e($a->titulo);
        }
        if ($alarmas->count() > self::MAX_LINEAS_RESUMEN) {
            $lineas[] = '… y ' . ($alarmas->count() - self::MAX_LINEAS_RESUMEN) . ' más.';
        }
        $lineas[] = '🕒 ' . now()->format('d/m/Y H:i:s');
        return implode("\n", $lineas);
    }

    private function mensajeResueltas(Collection $alarmas): string
    {
        $e = fn ($t) => htmlspecialchars((string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lineas = ['✅ <b>' . ($alarmas->count() === 1 ? 'Alarma resuelta' : $alarmas->count() . ' alarmas resueltas') . '</b>'];
        foreach ($alarmas->take(self::MAX_LINEAS_RESUMEN) as $a) {
            $duracion = $a->fecha_inicio && $a->fecha_resolucion ? $this->duracion($a->fecha_inicio->diffInSeconds($a->fecha_resolucion)) : null;
            $lineas[] = '• ' . $e($a->titulo) . ($duracion ? " — duró {$duracion}" : '');
        }
        if ($alarmas->count() > self::MAX_LINEAS_RESUMEN) {
            $lineas[] = '… y ' . ($alarmas->count() - self::MAX_LINEAS_RESUMEN) . ' más.';
        }
        return implode("\n", $lineas);
    }

    private function icono(?string $severidad): string
    {
        return $severidad === 'critica' ? '🔴' : '🟠';
    }

    private function duracion(float $s): string
    {
        $s = (int) $s;
        return match (true) {
            $s < 60 => "{$s} s",
            $s < 3600 => intdiv($s, 60) . ' min',
            $s < 86400 => intdiv($s, 3600) . ' h ' . intdiv($s % 3600, 60) . ' min',
            default => intdiv($s, 86400) . ' d ' . intdiv($s % 86400, 3600) . ' h',
        };
    }

    /** Enlace a la ficha solo si APP_URL es accesible desde fuera (no localhost). */
    private function enlace(?int $dispositivoId): ?string
    {
        $base = rtrim((string) config('app.url'), '/');
        if (!$dispositivoId || $base === '' || preg_match('#//(localhost|127\.)#', $base)) {
            return null;
        }
        return "{$base}/dispositivos/{$dispositivoId}";
    }
}
