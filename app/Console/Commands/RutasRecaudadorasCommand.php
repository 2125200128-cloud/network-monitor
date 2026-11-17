<?php

namespace App\Console\Commands;

use App\Models\Recaudadora;
use App\Support\Python;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Ruta de red de cada recaudadora (cada 6 h con nm-recaudadoras-rutas.timer): traceroute desde el servidor hasta su
 * FortiGate. El mapa de Topología la usa para dibujar por qué equipo del core llega cada sede (vía la MPLS).
 * A cada sede se le traza la IP que contesta: la administrativa si ha respondido alguna vez, si no la loopback.
 * Si esta vez no se llegó (sede caída) se conserva la última ruta completa que se midió.
 */
class RutasRecaudadorasCommand extends Command
{
    protected $signature = 'recaudadoras:rutas';

    protected $description = 'Mide por dónde llega cada recaudadora (traceroute) para dibujarla en la topología';

    public function handle(): int
    {
        $recas = Recaudadora::where('activo', true)
            ->where(fn ($q) => $q->whereNotNull('ip_administrativa')->orWhereNotNull('ip_loopback'))->get()->keyBy('id');
        $objetivos = $recas->map(fn (Recaudadora $r) => ['id' => $r->id,
            'ip' => ($r->admin_alguna_vez || !$r->ip_loopback) && $r->ip_administrativa ? $r->ip_administrativa : $r->ip_loopback])->values();
        if ($objetivos->isEmpty()) {
            $this->info('No hay recaudadoras con IP.');
            return self::SUCCESS;
        }

        $proceso = new Process([Python::binario(), base_path('worker/rutas_recaudadoras.py')], base_path(), Python::entorno());
        $proceso->setInput(json_encode($objetivos));
        $proceso->setTimeout(900);
        $proceso->run();
        $res = json_decode(trim($proceso->getOutput()), true);
        if (!is_array($res)) {
            $this->error('La medición falló: ' . mb_strimwidth(trim($proceso->getErrorOutput() ?: $proceso->getOutput()), 0, 300));
            return self::FAILURE;
        }

        $llegaron = 0;
        foreach ($res as $x) {
            $r = $recas->get($x['id'] ?? null);
            if (!$r || empty($x['saltos'])) {
                continue;
            }
            $llegaron += $x['llego'] ? 1 : 0;
            $previaCompleta = is_array($r->ruta) && ($r->ruta['llego'] ?? false);
            if ($x['llego'] || !$previaCompleta) {
                $r->forceFill(['ruta' => ['saltos' => $x['saltos'], 'llego' => (bool) $x['llego']], 'ruta_medida_en' => now()])->save();
            }
        }
        \App\Http\Controllers\TopologiaController::olvidarCacheGrafo();
        $this->info("Rutas medidas: {$llegaron} de {$objetivos->count()} recaudadoras llegaron hasta su FortiGate.");
        return self::SUCCESS;
    }
}
