<?php

namespace App\Console\Commands;

use App\Support\Python;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Equipos nuevos en la red (cada 30 min con nm-vigilar-red.timer): lee las tablas ARP (worker/tablas_arp.py), registra
 * cada MAC de la red RED y marca "nuevo" lo que nunca se había visto. La primera lectura es la línea base.
 * Solo se listan en la página (Equipos → Equipos nuevos); no se avisan por Telegram.
 */
class VigilarRedCommand extends Command
{
    protected $signature = 'red:vigilar';

    protected $description = 'Registra los aparatos de la red 10.4 y marca los que nunca se habían visto';

    public const RED = '10.4.0.0/16';

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
            $this->info('Línea base: ' . count($res['macs']) . ' aparatos registrados en ' . self::RED . ' (desde ahora se marcan los nuevos).');
            return self::SUCCESS;
        }
        $this->info(count($res['macs']) . ' aparatos en ' . self::RED . ", {$nuevos} nuevos.");
        return self::SUCCESS;
    }
}
