<?php

namespace App\Console\Commands;

use App\Support\Python;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Estado de las cámaras (cada minuto con nm-camaras-estado.timer): en línea si responde ping (o, si lo bloquea, su
 * puerto de video o web); caída tras FALLOS_PARA_CAIDA revisiones seguidas sin respuesta (un tropiezo no la marca).
 * Solo se muestra en la página Cámaras: no genera alarmas.
 */
class RevisarCamarasCommand extends Command
{
    protected $signature = 'camaras:revisar';

    protected $description = 'Revisa si cada cámara responde (en línea o caída)';

    public const FALLOS_PARA_CAIDA = 2;

    public function handle(): int
    {
        $camaras = DB::table('camaras_detectadas')->get(['id', 'ip', 'rtsp', 'http', 'https', 'estado', 'fallos_seguidos']);
        if ($camaras->isEmpty()) {
            return self::SUCCESS;
        }
        // Primero el puerto que se sabe abierto; si no se conoce ninguno, los tres
        $objetivos = $camaras->map(fn ($c) => ['id' => $c->id, 'ip' => $c->ip, 'puertos' => array_values(array_filter([
            $c->rtsp ? 554 : null, $c->http ? 80 : null, $c->https ? 443 : null,
        ])) ?: [554, 80, 443]])->values();

        $proceso = new Process([Python::binario(), base_path('worker/revisar_camaras.py')], base_path(), Python::entorno());
        $proceso->setInput(json_encode($objetivos));
        $proceso->setTimeout(120);
        $proceso->run();
        $resultados = collect(json_decode(trim($proceso->getOutput()), true) ?: [])->keyBy('id');
        if ($resultados->isEmpty()) {
            $this->error('La revisión falló: ' . mb_strimwidth(trim($proceso->getErrorOutput()), 0, 300));
            return self::FAILURE;
        }

        $ahora = now();
        foreach ($camaras as $c) {
            $r = $resultados[$c->id] ?? null;
            if (!$r) {
                continue;
            }
            $fallos = $r['ok'] ? 0 : min($c->fallos_seguidos + 1, 1000);
            // Caída solo confirmada; mientras no se confirma conserva su estado (una nueva sin revisar queda sin estado)
            $estado = $r['ok'] ? 'online' : ($fallos >= self::FALLOS_PARA_CAIDA ? 'offline' : $c->estado);
            $cambios = ['ultimo_chequeo' => $ahora, 'latencia_ms' => $r['ms'], 'fallos_seguidos' => $fallos, 'estado' => $estado];
            if ($estado !== $c->estado) {
                $cambios['estado_desde'] = $ahora;
            }
            DB::table('camaras_detectadas')->where('id', $c->id)->update($cambios);
        }

        $estados = DB::table('camaras_detectadas')->selectRaw("count(*) FILTER (WHERE estado = 'online') AS en_linea, count(*) FILTER (WHERE estado = 'offline') AS caidas")->first();
        $this->info("Cámaras: {$estados->en_linea} en línea, {$estados->caidas} caídas.");
        return self::SUCCESS;
    }
}
