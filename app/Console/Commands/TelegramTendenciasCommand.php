<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionGeneral;
use App\Services\DashboardMetricsService;
use App\Services\ImagenTendencias;
use App\Services\TelegramBot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Manda a Telegram las tendencias de la última hora (cada 2 h con nm-tendencias.timer): al grupo de redes las de
 * red y firewalls, al de servidores las de virtualización y sitios web (con un solo grupo, las dos imágenes van ahí).
 * Las gráficas terminan en el momento del envío y no dibujan lecturas viejas (ImagenTendencias::MINUTOS_VIGENTE).
 */
class TelegramTendenciasCommand extends Command
{
    protected $signature = 'telegram:tendencias {--archivo= : Carpeta donde guardar las imágenes en lugar de enviarlas (para revisarlas)}';

    protected $description = 'Envía a Telegram las gráficas de tendencias de la última hora';

    private const TITULOS = ['redes' => 'Tendencias de redes', 'servidores' => 'Tendencias de servidores'];

    public function handle(DashboardMetricsService $metricas, ImagenTendencias $imagen): int
    {
        $hasta = now();
        $desde = $hasta->copy()->subHour();
        $datos = $metricas->series('1h');
        $pngs = [];
        foreach (ImagenTendencias::GRUPOS as $grupo => $paneles) {
            $pngs[$grupo] = $imagen->png($datos, $paneles, self::TITULOS[$grupo], $desde, $hasta);
        }
        if (in_array(null, $pngs, true)) {
            $this->error('El servidor no tiene la extensión GD de PHP: no se pueden dibujar las gráficas.');
            return self::FAILURE;
        }

        if ($carpeta = $this->option('archivo')) {
            foreach ($pngs as $grupo => $png) {
                file_put_contents(rtrim($carpeta, '/\\') . DIRECTORY_SEPARATOR . "tendencias_{$grupo}.png", $png);
            }
            $this->info('Imágenes guardadas en ' . $carpeta);
            return self::SUCCESS;
        }

        $cfg = ConfiguracionGeneral::first();
        $bot = $cfg && $cfg->telegram_activo && $cfg->telegram_chat_id ? TelegramBot::desdeConfiguracion($cfg) : null;
        if (!$bot) {
            $this->line('Telegram no está activo: no se envía nada.');
            return self::SUCCESS;
        }
        // Mismo reparto que las alarmas: sin chat de servidores (o si es el mismo), todo va al de redes
        $redes = (string) $cfg->telegram_chat_id;
        $servidores = trim((string) $cfg->telegram_chat_id_servidores) ?: $redes;
        $envios = $servidores === $redes ? [$redes => ['redes', 'servidores']] : [$redes => ['redes'], $servidores => ['servidores']];

        $errores = 0;
        foreach ($envios as $chat => $grupos) {
            foreach ($grupos as $grupo) {
                $pie = '📈 <b>' . self::TITULOS[$grupo] . '</b> · última hora (' . $desde->format('H:i') . ' a ' . $hasta->format('H:i') . ')';
                try {
                    $bot->enviarFoto((string) $chat, $pngs[$grupo], $pie);
                } catch (\Throwable $e) {
                    $errores++;
                    Log::warning('Tendencias Telegram: ' . $e->getMessage());
                    $this->error($e->getMessage());
                }
            }
        }
        $this->info('Tendencias enviadas' . ($errores ? " con {$errores} error(es)." : '.'));
        return $errores ? self::FAILURE : self::SUCCESS;
    }
}
