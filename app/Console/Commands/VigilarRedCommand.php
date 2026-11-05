<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionGeneral;
use App\Services\TelegramBot;
use App\Support\Python;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Equipos nuevos en la red (cada 30 min con nm-vigilar-red.timer): lee las tablas ARP (worker/tablas_arp.py), registra
 * cada MAC de la red RED y marca "nuevo" lo que nunca se había visto. La primera lectura es la línea base (no avisa).
 * Los nuevos con MAC de fábrica se resumen en un solo mensaje al grupo de redes de Telegram; los de MAC aleatoria
 * (celulares y laptops que la cambian solos) solo se listan en la página.
 */
class VigilarRedCommand extends Command
{
    protected $signature = 'red:vigilar';

    protected $description = 'Registra los aparatos de la red 10.4 y avisa de los que nunca se habían visto';

    public const RED = '10.4.0.0/16';
    private const MAX_LINEAS_AVISO = 20;

    public function handle(): int
    {
        $proceso = new Process([Python::binario(), base_path('worker/tablas_arp.py'), database_path(BuscarCamarasCommand::OUI), self::RED],
            base_path(), Python::entorno(['NM_OBJETIVOS' => json_encode(BuscarCamarasCommand::objetivosArp())]));
        $proceso->setTimeout(600);
        $proceso->run();
        $res = json_decode(trim($proceso->getOutput()), true);
        if (!is_array($res) || !isset($res['macs'])) {
            $this->error('La lectura de las tablas ARP falló: ' . mb_strimwidth(trim($proceso->getErrorOutput() ?: $proceso->getOutput()), 0, 300));
            return self::FAILURE;
        }
        // Sin respuesta de los equipos de red no hay nada que comparar (no es que la red esté vacía)
        if (!$res['macs']) {
            $this->warn('Ningún equipo entregó su tabla ARP: no se compara nada.');
            return self::SUCCESS;
        }

        $lineaBase = !DB::table('equipos_vistos')->exists();
        $ahora = now();
        $nuevos = 0;
        foreach ($res['macs'] as $m) {
            $datos = ['ip' => $m['ip'], 'fabricante' => $m['fabricante'] ? mb_substr($m['fabricante'], 0, 255) : null,
                'mac_aleatoria' => $m['aleatoria'], 'visto_en' => $m['visto_en'], 'visto_en_ip' => $m['visto_en_ip'], 'ultima_vez' => $ahora];
            if (DB::table('equipos_vistos')->where('mac', $m['mac'])->update($datos) === 0) {
                DB::table('equipos_vistos')->insert($datos + ['mac' => $m['mac'], 'primera_vez' => $ahora, 'estado' => $lineaBase ? 'base' : 'nuevo']);
                $nuevos += $lineaBase ? 0 : 1;
            }
        }

        if ($lineaBase) {
            $this->info('Línea base: ' . count($res['macs']) . ' aparatos registrados en ' . self::RED . ' (desde ahora se avisa de los nuevos).');
            return self::SUCCESS;
        }
        $avisados = $this->avisar();
        $this->info(count($res['macs']) . " aparatos en " . self::RED . ", {$nuevos} nuevos" . ($avisados ? ", {$avisados} avisados por Telegram" : '') . '.');
        return self::SUCCESS;
    }

    /** Un solo mensaje al grupo de redes con los nuevos de MAC de fábrica que aún no se avisan. Devuelve cuántos. */
    private function avisar(): int
    {
        $pendientes = DB::table('equipos_vistos')->where('estado', 'nuevo')->where('mac_aleatoria', false)->whereNull('avisado_en')
            ->orderBy('primera_vez')->get();
        $cfg = ConfiguracionGeneral::first();
        $bot = $cfg && $cfg->telegram_activo && $cfg->telegram_chat_id ? TelegramBot::desdeConfiguracion($cfg) : null;
        if ($pendientes->isEmpty() || !$bot) {
            return 0;
        }

        try {
            $bot->enviarMensaje((string) $cfg->telegram_chat_id, self::mensaje($pendientes));   // grupo de redes
        } catch (\Throwable $ex) {
            Log::warning('Equipos nuevos: no se pudo avisar por Telegram: ' . $ex->getMessage());
            return 0;
        }
        DB::table('equipos_vistos')->whereIn('id', $pendientes->pluck('id'))->update(['avisado_en' => now()]);
        return $pendientes->count();
    }

    /** Texto del resumen de Telegram (HTML de Telegram). */
    public static function mensaje($pendientes): string
    {
        $e = fn ($t) => htmlspecialchars((string) $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lineas = ['🆕 <b>' . ($pendientes->count() === 1 ? '1 equipo nuevo' : "{$pendientes->count()} equipos nuevos") . ' en la red 10.4</b>',
            'Nunca se habían visto. Revísalos en el NOC: Equipos → Equipos nuevos.'];
        foreach ($pendientes->take(self::MAX_LINEAS_AVISO) as $p) {
            $lineas[] = '• <code>' . $e($p->ip) . '</code> · ' . $e($p->fabricante ?: 'fabricante desconocido')
                . ' · visto en ' . $e(strtok((string) $p->visto_en, '.')) . ' · <code>' . $e($p->mac) . '</code>';
        }
        if ($pendientes->count() > self::MAX_LINEAS_AVISO) {
            $lineas[] = '… y ' . ($pendientes->count() - self::MAX_LINEAS_AVISO) . ' más.';
        }
        return implode("\n", $lineas);
    }
}
