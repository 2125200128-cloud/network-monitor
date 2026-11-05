<?php

namespace App\Console\Commands;

use App\Models\Dispositivo;
use App\Support\Python;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Busca cámaras de seguridad en las tablas ARP de los equipos de red (worker/buscar_camaras.py) y actualiza
 * camaras_detectadas. Solo inventario: no las monitorea ni genera alarmas. La corre nm-camaras.timer cada noche
 * y el botón "Buscar cámaras" de Equipos.
 */
class BuscarCamarasCommand extends Command
{
    protected $signature = 'camaras:buscar';

    protected $description = 'Busca cámaras de seguridad en las tablas ARP de los equipos de red';

    /**
     * Prefijos de MAC de fabricantes de cámaras, extraídos del registro OUI oficial de la IEEE
     * (https://standards-oui.ieee.org/oui/oui.csv). Para incluir un fabricante nuevo se vuelve a extraer.
     */
    private const OUI_CAMARAS = 'data/oui_camaras.csv';

    /** Solo se guardan las cámaras de esta red (sector 10.4); las de otras dependencias no se muestran. */
    private const RED_CAMARAS = '10.4.0.0/16';

    /** Tipos de equipo que pueden tener tabla ARP de otras redes (los que enrutan o gestionan VLANs). */
    private const TIPOS_CON_ARP = ['switch', 'router', 'firewall', 'wlc', 'desconocido'];

    public function handle(): int
    {
        $oui = database_path(self::OUI_CAMARAS);

        $objetivos = Dispositivo::with('telemetriaChasis')->where('estado', '!=', 'offline')->whereNotNull('ip')->get()
            ->filter(fn ($d) => in_array($d->tipo_dispositivo, self::TIPOS_CON_ARP, true))
            ->map(fn ($d) => ['nombre' => $d->nombre, 'ip' => $d->ip, 'comunidad' => $d->comunidadEfectiva()])->values();

        // Las comunidades viajan por entorno: no quedan en la línea de comandos ni en la salida
        $proceso = new Process([Python::binario(), base_path('worker/buscar_camaras.py'), $oui], base_path(),
            Python::entorno(['NM_OBJETIVOS' => json_encode($objetivos)]));
        $proceso->setTimeout(600);
        $proceso->run();
        $res = json_decode(trim($proceso->getOutput()), true);
        if (!is_array($res)) {
            $this->error('La búsqueda falló: ' . mb_strimwidth(trim($proceso->getErrorOutput() ?: $proceso->getOutput()), 0, 300));
            return self::FAILURE;
        }

        [$red, $bits] = explode('/', self::RED_CAMARAS);
        $mascara = -1 << (32 - (int) $bits);
        $enRed = fn (string $ip) => ip2long($ip) !== false && (ip2long($ip) & $mascara) === (ip2long($red) & $mascara);
        $camaras = array_values(array_filter($res['camaras'], fn ($c) => $enRed($c['ip'])));
        // Las que quedaron guardadas de antes fuera de la red (p. ej. si cambió RED_CAMARAS) se quitan
        $fuera = DB::table('camaras_detectadas')->whereRaw('NOT (inet(ip) << ?::cidr)', [self::RED_CAMARAS])->delete();

        $ahora = now();
        $nuevas = 0;
        foreach ($camaras as $c) {
            $datos = ['ip' => $c['ip'], 'fabricante' => mb_substr($c['fabricante'], 0, 255), 'visto_en' => $c['visto_en'],
                'visto_en_ip' => $c['visto_en_ip'], 'rtsp' => $c['rtsp'], 'http' => $c['http'], 'https' => $c['https'], 'ultima_vez' => $ahora];
            if (DB::table('camaras_detectadas')->where('mac', $c['mac'])->update($datos) === 0) {
                DB::table('camaras_detectadas')->insert($datos + ['mac' => $c['mac'], 'primera_vez' => $ahora]);
                $nuevas++;
            }
        }

        $conTabla = collect($res['equipos'])->where('entradas', '>', 0)->count();
        $this->info(count($camaras) . ' cámaras en ' . self::RED_CAMARAS . " ({$nuevas} nuevas) en las tablas ARP de {$conTabla} de "
            . count($res['equipos']) . ' equipos' . ($fuera ? "; se quitaron {$fuera} de otras redes" : '') . '.');
        return self::SUCCESS;
    }
}
