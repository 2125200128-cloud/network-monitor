<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionGeneral;
use App\Models\Recaudadora;
use App\Models\RecaudadoraEquipo;
use App\Models\RecaudadoraEvento;
use App\Services\TelegramBot;
use App\Support\Python;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

/**
 * Revisa cada minuto (nm-recaudadoras.timer) si cada recaudadora tiene comunicación:
 *   - ping a su IP administrativa (la puerta de enlace de su red) y a su loopback;
 *   - online si responde la IP administrativa; tras FALLOS_PARA_CAIDA revisiones seguidas sin respuesta queda
 *     «lan» si la loopback sí responde (el equipo está, falla su red interna) u «offline» si no responde nada.
 * Sus equipos (antenas, kioscos…) igual, por ping o, si lo bloquean, por un puerto TCP común.
 * Cada caída queda como evento (para la disponibilidad) y se avisa al grupo de redes de Telegram, en un solo mensaje
 * por revisión. La primera revisión de una recaudadora nueva no avisa (no se sabe si estaba bien antes).
 */
class RevisarRecaudadorasCommand extends Command
{
    protected $signature = 'recaudadoras:revisar {--sin-avisos : Revisa y guarda el estado, pero no manda Telegram}';

    protected $description = 'Revisa la comunicación de cada recaudadora y sus equipos';

    public const FALLOS_PARA_CAIDA = 3;

    /** Sin revisión en más de estos minutos (el temporizador corre cada minuto) se considera que el monitoreo se detuvo. */
    private const HUECO_MINUTOS = 5;
    private const PUERTOS_EQUIPO = [443, 80, 22, 3389];
    private const MAX_EN_AVISO = 25;
    /** Desde cuántas caídas a la vez el aviso sugiere una falla general (enlace del proveedor o core) */
    public const CAIDA_MASIVA = 10;

    private array $avisos = ['caidas' => [], 'lan' => [], 'recuperadas' => [], 'equipos_caidos' => [], 'equipos_recuperados' => []];

    /** Esta revisión de cada recaudadora, para el historial de ping de su ficha. */
    private array $mediciones = [];

    public function handle(): int
    {
        $recas = Recaudadora::where('activo', true)->get()->keyBy('id');
        $equipos = RecaudadoraEquipo::whereIn('recaudadora_id', $recas->keys())->get();

        $objetivos = [];
        foreach ($recas as $r) {
            if ($r->ip_administrativa) {
                $objetivos[] = ['id' => "a{$r->id}", 'ip' => $r->ip_administrativa, 'puertos' => []];
            }
            if ($r->ip_loopback) {
                $objetivos[] = ['id' => "l{$r->id}", 'ip' => $r->ip_loopback, 'puertos' => []];
            }
        }
        foreach ($equipos as $e) {
            $objetivos[] = ['id' => "e{$e->id}", 'ip' => $e->ip, 'puertos' => self::PUERTOS_EQUIPO];
        }
        if (!$objetivos) {
            return self::SUCCESS;
        }

        // El mismo revisor de las cámaras: ping y, si no contesta, conexión TCP a los puertos indicados
        $proceso = new Process([Python::binario(), base_path('worker/revisar_camaras.py')], base_path(), Python::entorno());
        $proceso->setInput(json_encode($objetivos));
        $proceso->setTimeout(150);
        $proceso->run();
        $res = collect(json_decode(trim($proceso->getOutput()), true) ?: [])->keyBy('id');
        if ($res->isEmpty()) {
            $this->error('La revisión falló: ' . mb_strimwidth(trim($proceso->getErrorOutput()), 0, 300));
            return self::FAILURE;
        }

        $ahora = now();
        foreach ($recas as $r) {
            $this->evaluarRecaudadora($r, $res->get("a{$r->id}"), $res->get("l{$r->id}"), $ahora);
        }
        foreach ($equipos as $e) {
            $this->evaluarEquipo($e, $recas[$e->recaudadora_id], $res->get("e{$e->id}"), $ahora);
        }
        // Historial de las gráficas: si falla, la revisión y los avisos siguen
        try {
            \App\Services\Recaudadoras\HistorialPing::registrar($this->mediciones);
        } catch (\Throwable $e) {
            $this->warn('No se pudo guardar el historial de ping: ' . $e->getMessage());
        }

        if (!$this->option('sin-avisos')) {
            $this->avisar();
        }

        $conteo = Recaudadora::where('activo', true)->whereNotNull('estado')->get()->countBy('estado');
        $this->info(sprintf('Recaudadoras: %d en servicio, %d sin comunicación, %d con falla de red interna.',
            $conteo['online'] ?? 0, $conteo['offline'] ?? 0, $conteo['lan'] ?? 0));
        return self::SUCCESS;
    }

    private function evaluarRecaudadora(Recaudadora $r, ?array $admin, ?array $loopback, Carbon $ahora): void
    {
        if (!$admin && !$loopback) {
            return;   // sin IP que revisar
        }
        $adminOk = $admin ? (bool) $admin['ok'] : null;
        $lbOk = $loopback ? (bool) $loopback['ok'] : null;
        $adminAlgunaVez = $r->admin_alguna_vez || $adminOk;
        // Tiene servicio si responde su IP administrativa. Si esa IP nunca ha respondido (ping bloqueado en esa
        // interfaz) o no la tiene, cuenta la loopback. «lan» solo si la IP administrativa respondía y dejó de hacerlo
        // mientras la loopback sigue respondiendo.
        $ok = $adminOk || (!$adminAlgunaVez && $lbOk);
        $this->mediciones[] = ['recaudadora_id' => $r->id, 'medido_en' => $ahora, 'ok' => (bool) $ok,
            'admin_ok' => $adminOk, 'loopback_ok' => $lbOk,
            'admin_ms' => $adminOk ? $admin['ms'] : null, 'loopback_ms' => $lbOk ? $loopback['ms'] : null];
        $corte =$this->cortarHueco($r->ultimo_chequeo, $ahora, $ok,
            RecaudadoraEvento::where('recaudadora_id', $r->id)->whereNull('equipo_id')->whereNull('fin'));
        $fallos = $ok ? 0 : min($r->fallos_seguidos + 1, 1000);
        $anterior = $r->estado;
        $nuevo = match (true) {
            $ok => 'online',
            $fallos >= self::FALLOS_PARA_CAIDA => ($adminAlgunaVez && $lbOk) ? 'lan' : 'offline',
            default => $anterior,   // aún sin confirmar: conserva su estado
        };

        $r->fill([
            'ultimo_chequeo' => $ahora, 'fallos_seguidos' => $fallos,
            'admin_ok' => $adminOk, 'loopback_ok' => $lbOk,
            'admin_alguna_vez' => $adminAlgunaVez, 'loopback_alguna_vez' => $r->loopback_alguna_vez || $lbOk,
            'latencia_ms' => $ok ? ($adminOk ? $admin['ms'] : $loopback['ms']) : null,
        ]);

        if ($nuevo !== $anterior) {
            $r->estado = $nuevo;
            $r->estado_desde = $nuevo === 'online' ? $ahora : $this->inicioCaida($ahora, $fallos);
            // Tras un hueco del monitoreo solo se cuenta lo que se vio (hasta la última revisión de antes)
            $duracion = $anterior && $r->getOriginal('estado_desde') ? Carbon::parse($r->getOriginal('estado_desde'))->diffInSeconds($corte ?? $ahora, true) : null;

            // Cierra lo que estuviera abierto y abre la nueva caída
            RecaudadoraEvento::where('recaudadora_id', $r->id)->whereNull('equipo_id')->whereNull('fin')->update(['fin' => $ahora]);
            if ($nuevo !== 'online') {
                RecaudadoraEvento::create(['recaudadora_id' => $r->id, 'tipo' => $nuevo === 'lan' ? 'lan' : 'sin_comunicacion',
                    'inicio' => $r->estado_desde]);
            }

            if ($anterior !== null) {   // la primera revisión no avisa
                match ($nuevo) {
                    'offline' => $this->avisos['caidas'][] = $r,
                    'lan' => $this->avisos['lan'][] = $r,
                    'online' => $this->avisos['recuperadas'][] = [$r, $duracion],
                };
            }
        }
        $r->save();
    }

    private function evaluarEquipo(RecaudadoraEquipo $e, Recaudadora $r, ?array $res, Carbon $ahora): void
    {
        if (!$res) {
            return;
        }
        $ok = (bool) $res['ok'];
        $corte = $this->cortarHueco($e->ultimo_chequeo, $ahora, $ok, RecaudadoraEvento::where('equipo_id', $e->id)->whereNull('fin'));
        $fallos = $ok ? 0 : min($e->fallos_seguidos + 1, 1000);
        $anterior = $e->estado;
        $nuevo = $ok ? 'online' : ($fallos >= self::FALLOS_PARA_CAIDA ? 'offline' : $anterior);
        $yaRespondio = (bool) $e->alguna_vez;   // antes de esta revisión
        $e->fill(['ultimo_chequeo' => $ahora, 'fallos_seguidos' => $fallos, 'latencia_ms' => $ok ? $res['ms'] : null,
            'alguna_vez' => $yaRespondio || $ok]);

        if ($nuevo !== $anterior) {
            $duracion = $anterior && $e->estado_desde ? $e->estado_desde->diffInSeconds($corte ?? $ahora, true) : null;
            $e->estado = $nuevo;
            $e->estado_desde = $nuevo === 'online' ? $ahora : $this->inicioCaida($ahora, $fallos);
            RecaudadoraEvento::where('equipo_id', $e->id)->whereNull('fin')->update(['fin' => $ahora]);
            if ($nuevo === 'offline' && $yaRespondio) {   // si nunca respondió no hay caída que registrar
                RecaudadoraEvento::create(['recaudadora_id' => $r->id, 'equipo_id' => $e->id, 'tipo' => 'equipo', 'inicio' => $e->estado_desde]);
            }
            // Si la recaudadora completa no tiene comunicación, ya se avisó por ella: no se repite por cada equipo.
            // Un equipo que nunca había respondido (kiosco con IP equivocada o ping bloqueado) no avisa ni al caer
            // ni la primera vez que contesta: no hubo servicio que se perdiera o recuperara.
            if ($anterior !== null && $yaRespondio && $r->estado === 'online') {
                $nuevo === 'offline'
                    ? $this->avisos['equipos_caidos'][] = $e->setRelation('recaudadora', $r)
                    : $this->avisos['equipos_recuperados'][] = [$e->setRelation('recaudadora', $r), $duracion];
            }
        }
        $e->save();
    }

    /**
     * Si el monitoreo estuvo detenido (servidor apagado, mantenimiento, temporizador parado), no se sabe qué pasó en
     * ese hueco: la caída abierta se corta un minuto después de la última revisión y, si sigue sin responder, se abre
     * otra desde ahora. Así el hueco no cuenta como caída. Devuelve el momento del corte, o null si no hubo hueco.
     */
    private function cortarHueco(?Carbon $previo, Carbon $ahora, bool $ok, $abiertos): ?Carbon
    {
        if (!$previo || $previo->gte($ahora->copy()->subMinutes(self::HUECO_MINUTOS))) {
            return null;
        }
        $corte = $previo->copy()->addMinute();
        foreach ($abiertos->get() as $ev) {
            $ev->update(['fin' => $corte]);
            if (!$ok) {
                RecaudadoraEvento::create(['recaudadora_id' => $ev->recaudadora_id, 'equipo_id' => $ev->equipo_id,
                    'tipo' => $ev->tipo, 'inicio' => $ahora]);
            }
        }
        return $corte;
    }

    /** La caída empezó en la primera revisión fallida (se confirma unas revisiones después). */
    private function inicioCaida(Carbon $ahora, int $fallos): Carbon
    {
        return $ahora->copy()->subMinutes(max(0, min($fallos, self::FALLOS_PARA_CAIDA) - 1));
    }

    private function avisar(): void
    {
        if (!array_filter($this->avisos)) {
            return;
        }
        $cfg = ConfiguracionGeneral::first();
        $imagen = $this->imagen();
        $bot = $cfg && $cfg->telegram_activo && $cfg->telegram_chat_id ? TelegramBot::desdeConfiguracion($cfg) : null;
        if ($bot) {
            $chat = (string) $cfg->telegram_chat_id;
            $mensaje = $this->mensaje();
            $foto = $cfg->telegram_imagenes ? $imagen : null;
            $enviado = false;
            try {
                // Si el texto cabe como pie de la foto va todo en un solo mensaje; si no, el texto y luego la foto
                if ($foto && mb_strlen($mensaje) <= TelegramBot::MAX_PIE_FOTO) {
                    $bot->enviarFoto($chat, $foto, $mensaje);
                    $enviado = true;
                } else {
                    $bot->enviarMensaje($chat, $mensaje);
                    $enviado = true;
                    if ($foto) {
                        $bot->enviarFoto($chat, $foto, 'Mapa y datos de las recaudadoras afectadas');
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('No se pudo avisar por Telegram: ' . $e->getMessage());
                if (!$enviado) {   // falló la foto: que al menos llegue el texto
                    try {
                        $bot->enviarMensaje($chat, $mensaje);
                    } catch (\Throwable) {
                    }
                }
            }
        }
        // Por correo, al grupo de redes (AlertasCorreo no lanza excepciones y no hace nada si el correo no está activado)
        [$asunto, $secciones] = $this->correo();
        app(\App\Services\AlertasCorreo::class)->avisoRecaudadoras($asunto, $secciones, $imagen);
    }

    /** Mapa con las recaudadoras de este aviso y su ficha (solo si alguna cayó o tiene falla de red interna). */
    private function imagen(): ?string
    {
        if (!$this->avisos['caidas'] && !$this->avisos['lan']) {
            return null;
        }
        try {
            return app(\App\Services\Recaudadoras\ImagenRecaudadoras::class)->png($this->avisos['caidas'], $this->avisos['lan']);
        } catch (\Throwable $e) {
            $this->warn('No se pudo generar la imagen de recaudadoras: ' . $e->getMessage());
            return null;
        }
    }

    /** Mismo aviso que el de Telegram, en secciones de texto para la plantilla del correo. @return array{0: string, 1: array} */
    private function correo(): array
    {
        $linea = fn (Recaudadora $r) => $r->titulo() . ' · ' . ($r->ip_administrativa ?: $r->ip_loopback)
            . ($r->municipio && !str_contains($r->nombre, $r->municipio) ? ' · ' . $r->municipio : '')
            . (($enlace = $this->enlace($r)) ? ' · ' . $enlace : '');
        $lista = function (array $items, callable $fmt) {
            $lineas = array_map($fmt, array_slice($items, 0, self::MAX_EN_AVISO));
            if (count($items) > self::MAX_EN_AVISO) {
                $lineas[] = '… y ' . (count($items) - self::MAX_EN_AVISO) . ' más (ver la página Recaudadoras)';
            }
            return $lineas;
        };
        $a = $this->avisos;
        $secciones = [];
        if ($a['caidas']) {
            $secciones[] = ['titulo' => 'Sin comunicación', 'color' => '#dc2626', 'lineas' => $lista($a['caidas'], $linea),
                'nota' => count($a['caidas']) >= self::CAIDA_MASIVA
                    ? count($a['caidas']) . ' recaudadoras perdieron comunicación a la vez: probable falla general del enlace del proveedor (MPLS) o del core, no de cada oficina.'
                    : 'No responden ni su IP administrativa ni su loopback.'];
        }
        if ($a['lan']) {
            $secciones[] = ['titulo' => 'Falla de red interna', 'color' => '#d97706', 'nota' => 'El equipo responde por su loopback, su red no.', 'lineas' => $lista($a['lan'], $linea)];
        }
        if ($a['equipos_caidos']) {
            $secciones[] = ['titulo' => 'Equipos sin respuesta', 'color' => '#dc2626',
                'lineas' => $lista($a['equipos_caidos'], fn ($q) => $q->nombre . ' · ' . $q->ip . ' en ' . $q->recaudadora->titulo())];
        }
        if ($a['recuperadas']) {
            $secciones[] = ['titulo' => 'Recuperadas', 'color' => '#059669',
                'lineas' => $lista($a['recuperadas'], fn ($x) => $x[0]->titulo() . ($x[1] ? ' · estuvo caída ' . self::duracion($x[1]) : ''))];
        }
        if ($a['equipos_recuperados']) {
            $secciones[] = ['titulo' => 'Equipos recuperados', 'color' => '#059669',
                'lineas' => $lista($a['equipos_recuperados'], fn ($x) => $x[0]->nombre . ' en ' . $x[0]->recaudadora->titulo() . ($x[1] ? ' · ' . self::duracion($x[1]) : ''))];
        }

        $n = fn (string $k) => count($a[$k]);
        $partes = array_filter([
            $n('caidas') ? $n('caidas') . ' sin comunicación' : null,
            $n('lan') ? $n('lan') . ' con falla de red interna' : null,
            $n('equipos_caidos') ? $n('equipos_caidos') . ($n('equipos_caidos') === 1 ? ' equipo sin respuesta' : ' equipos sin respuesta') : null,
            $n('recuperadas') ? $n('recuperadas') . ($n('recuperadas') === 1 ? ' recuperada' : ' recuperadas') : null,
            $n('equipos_recuperados') ? $n('equipos_recuperados') . ($n('equipos_recuperados') === 1 ? ' equipo recuperado' : ' equipos recuperados') : null,
        ]);
        // Con una sola recaudadora afectada, el asunto la nombra
        $sola = $n('caidas') + $n('lan') === 1 && !$n('recuperadas') && !$n('equipos_caidos') && !$n('equipos_recuperados') ?($a['caidas'][0] ?? $a['lan'][0]) : null;
        $asunto = $sola ? ($n('caidas') ? 'Sin comunicación: ' : 'Falla de red interna: ') . $sola->titulo() : implode(', ', $partes);
        return [$asunto, $secciones];
    }

    private function mensaje(): string
    {
        $e = fn ($t) => htmlspecialchars((string) $t, ENT_QUOTES);
        $linea = fn (Recaudadora $r) => '• ' . $e($r->titulo()) . ' <code>' . $e($r->ip_administrativa ?: $r->ip_loopback) . '</code>'
            . ($r->municipio && !str_contains($r->nombre, $r->municipio) ? ' · ' . $e($r->municipio) : '')
            . (($enlace = $this->enlace($r)) ? ' · ' . $e($enlace) : '');
        $lista = function (array $items, callable $fmt) {
            $lineas = array_map($fmt, array_slice($items, 0, self::MAX_EN_AVISO));
            if (count($items) > self::MAX_EN_AVISO) {
                $lineas[] = '… y ' . (count($items) - self::MAX_EN_AVISO) . ' más (ver la página Recaudadoras)';
            }
            return implode("\n", $lineas);
        };

        $partes = ['🏛 <b>Recaudadoras</b>'];
        if (count($this->avisos['caidas']) >= self::CAIDA_MASIVA) {
            $partes[] = '⚠️ <b>' . count($this->avisos['caidas']) . ' recaudadoras perdieron comunicación a la vez.</b> '
                . 'Probable falla general del enlace del proveedor (MPLS) o del core, no de cada oficina.';
        }
        if ($this->avisos['caidas']) {
            $partes[] = "🔴 <b>Sin comunicación</b> (no responden ni su IP administrativa ni su loopback)\n" . $lista($this->avisos['caidas'], $linea);
        }
        if ($this->avisos['lan']) {
            $partes[] = "🟠 <b>Falla de red interna</b> (el equipo responde por su loopback, su red no)\n" . $lista($this->avisos['lan'], $linea);
        }
        if ($this->avisos['recuperadas']) {
            $partes[] = "🟢 <b>Recuperadas</b>\n" . $lista($this->avisos['recuperadas'],
                fn ($x) => '• ' . $e($x[0]->titulo()) . ($x[1] ? ' · estuvo caída ' . self::duracion($x[1]) : ''));
        }
        if ($this->avisos['equipos_caidos']) {
            $partes[] = "🔴 <b>Equipos sin respuesta</b>\n" . $lista($this->avisos['equipos_caidos'],
                fn ($q) => '• ' . $e($q->nombre) . ' <code>' . $e($q->ip) . '</code> en ' . $e($q->recaudadora->titulo()));
        }
        if ($this->avisos['equipos_recuperados']) {
            $partes[] = "🟢 <b>Equipos recuperados</b>\n" . $lista($this->avisos['equipos_recuperados'],
                fn ($x) => '• ' . $e($x[0]->nombre) . ' en ' . $e($x[0]->recaudadora->titulo()) . ($x[1] ? ' · ' . self::duracion($x[1]) : ''));
        }
        return implode("\n\n", $partes);
    }

    /** Enlace principal para el aviso: tipo y velocidad, y la referencia para levantar el reporte con el proveedor. */
    private function enlace(Recaudadora $r): string
    {
        $partes = array_filter([$r->tipo_enlace, $r->uninet ? "Uninet {$r->uninet}" : null, $r->referencia ? "ref. {$r->referencia}" : null]);
        return implode(' ', $partes);
    }

    public static function duracion(int $segundos): string
    {
        return match (true) {
            $segundos < 3600 => max(1, intdiv($segundos, 60)) . ' min',
            $segundos < 86400 => intdiv($segundos, 3600) . ' h ' . intdiv($segundos % 3600, 60) . ' min',
            default => intdiv($segundos, 86400) . ' d ' . intdiv($segundos % 86400, 3600) . ' h',
        };
    }
}
