<?php

namespace App\Services\Recaudadoras;

use App\Models\Recaudadora;
use App\Models\RecaudadoraEquipo;
use App\Support\Python;
use Symfony\Component\Process\Process;

/**
 * Busca qué equipos contestan en la red interna de una recaudadora (worker/escanear_red.py) para dar de alta kioscos,
 * PCs, impresoras o antenas sin conocer su IP. El FortiGate no entrega su tabla ARP por SNMP, así que se barre el segmento.
 *
 * El segmento no viene en el Excel: se toma el bloque más grande (hasta /24) alrededor de la IP administrativa que no
 * incluya la IP administrativa de otra recaudadora. Varias comparten /24 en bloques de /27 (10.4.107.129, .161…).
 */
class EscanerRedRecaudadora
{
    private const PREFIJO_MAYOR = 24;
    private const PREFIJO_MENOR = 29;

    /** ['red' => '10.4.28.0/24', 'ips' => [...]] sin la red, la difusión ni la IP administrativa; null si no tiene IP. */
    public static function segmento(Recaudadora $r): ?array
    {
        $ip = ip2long((string) $r->ip_administrativa);
        if ($ip === false) {
            return null;
        }
        $otras = Recaudadora::where('id', '!=', $r->id)->whereNotNull('ip_administrativa')->pluck('ip_administrativa')
            ->map(fn ($x) => ip2long($x))->filter(fn ($x) => $x !== false && $x !== $ip)->all();

        for ($prefijo = self::PREFIJO_MAYOR; $prefijo <= self::PREFIJO_MENOR; $prefijo++) {
            $mascara = (-1 << (32 - $prefijo)) & 0xFFFFFFFF;
            $red = $ip & $mascara;
            $difusion = $red | (~$mascara & 0xFFFFFFFF);
            $ajena = array_filter($otras, fn ($o) => $o >= $red && $o <= $difusion);
            // La IP administrativa en la difusión o en la red no es una puerta de enlace: ese bloque no sirve
            if (!$ajena && $ip !== $red && $ip !== $difusion) {
                $ips = [];
                for ($x = $red + 1; $x < $difusion; $x++) {
                    if ($x !== $ip) {
                        $ips[] = long2ip($x);
                    }
                }
                return ['red' => long2ip($red) . "/{$prefijo}", 'ips' => $ips];
            }
        }
        return null;
    }

    /**
     * @return array{red: string, segundos: float, equipos: list<array{ip: string, ms: ?float, puertos: int[], nombre: ?string, tipo: string, registrado: ?string}>}
     */
    public function escanear(Recaudadora $r): array
    {
        $seg = self::segmento($r);
        if (!$seg) {
            throw new \RuntimeException('No tiene una IP administrativa con la que saber cuál es su red.');
        }
        $proceso = new Process([Python::binario(), base_path('worker/escanear_red.py')], base_path(), Python::entorno());
        $proceso->setInput(json_encode(['ips' => $seg['ips']]));
        $proceso->setTimeout(120);
        $proceso->run();
        $res = json_decode(trim($proceso->getOutput()), true);
        if (!is_array($res) || !isset($res['equipos'])) {
            throw new \RuntimeException('La búsqueda falló: ' . mb_strimwidth(trim($proceso->getErrorOutput() ?: $proceso->getOutput()), 0, 200));
        }

        $registrados = $r->equipos()->pluck('nombre', 'ip');
        $equipos = array_map(fn ($e) => $e + [
            'tipo' => self::tipoProbable($e['puertos'], $e['nombre']),
            'registrado' => $registrados[$e['ip']] ?? null,
        ], $res['equipos']);
        usort($equipos, fn ($a, $b) => ip2long($a['ip']) <=> ip2long($b['ip']));

        return ['red' => $seg['red'], 'segundos' => $res['segundos'] ?? null, 'equipos' => $equipos];
    }

    /** Tipo de equipo más probable por su nombre o sus puertos (el operador lo puede cambiar al darlo de alta). */
    public static function tipoProbable(array $puertos, ?string $nombre): string
    {
        $n = mb_strtolower((string) $nombre);
        foreach (['kiosco' => ['kiosk', 'kiosc'], 'impresora' => ['print', 'impres', 'npi', 'brw', 'epson'],
                  'ap' => ['-ap', 'ap-', 'wifi', 'wap'], 'switch' => ['sw-', 'sw_', 'switch'], 'antena' => ['antena', 'ptp', 'ubnt']] as $tipo => $pistas) {
            foreach ($pistas as $p) {
                if (str_contains($n, $p)) {
                    return $tipo;
                }
            }
        }
        return match (true) {
            (bool) array_intersect($puertos, [9100, 631]) => 'impresora',
            (bool) array_intersect($puertos, [3389, 445, 135]) => 'pc',
            in_array(554, $puertos, true) => 'otro',
            (bool) array_intersect($puertos, [22, 23]) && (bool) array_intersect($puertos, [80, 443]) => 'switch',
            default => 'otro',
        };
    }
}
