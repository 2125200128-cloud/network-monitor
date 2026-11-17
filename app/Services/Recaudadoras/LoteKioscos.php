<?php

namespace App\Services\Recaudadoras;

use App\Models\Recaudadora;

/**
 * Alta en bloque de kioscos y sus terminales de pago a partir de una lista pegada, una línea por kiosco:
 *
 *   Reca 000 1 10.4.6.9 terminal 10.4.6.61      → Kiosco 1 (10.4.6.9) y Terminal de pago 1 (10.4.6.61) en la Reca 0
 *   Reca 094 10.4.14.20 /Sin ip de terminal     → Kiosco (10.4.14.20) en la Reca 94, sin terminal
 *
 * La primera IP es la del kiosco; la que va después de «terminal» (o «TPV», «pinpad») es la de su terminal.
 * Las líneas sin recaudadora ni IP (títulos como «*IPS KIOSCOS*») se ignoran.
 */
class LoteKioscos
{
    private const IP = '(?<![\d.])(\d{1,3}(?:\.\d{1,3}){3})(?![\d.])';

    /**
     * @return list<array{linea: string, recaudadora: ?Recaudadora, equipos: list<array{tipo: string, nombre: string, ip: string, notas: ?string}>, error: ?string}>
     */
    public function interpretar(string $texto): array
    {
        $recas = Recaudadora::where('tipo', 'recaudadora')->get()->keyBy('clave');
        $filas = [];
        foreach (preg_split('/\R/', $texto) as $linea) {
            $linea = trim($linea, " \t*)(");
            if ($linea === '') {
                continue;
            }
            $tieneIp = preg_match('/' . self::IP . '/', $linea);
            if (!preg_match('/\b(?:reca|rec|r)\.?\s*0*(\d+[a-z]?)\b/iu', $linea, $mr, PREG_OFFSET_CAPTURE)) {
                if ($tieneIp) {
                    $filas[] = ['linea' => $linea, 'recaudadora' => null, 'equipos' => [], 'error' => 'No dice de qué recaudadora es (empieza con «Reca 12 …»).'];
                }
                continue;   // título u otra línea sin datos
            }
            $numero = strtoupper($mr[1][0]);
            $reca = $recas['R-' . $numero] ?? null;
            if (!$reca) {
                $filas[] = ['linea' => $linea, 'recaudadora' => null, 'equipos' => [], 'error' => "No existe la recaudadora {$numero} (¿ya se importó el Excel?)."];
                continue;
            }

            // Lo que va después de «Reca N»: [número de kiosco] IP [terminal IP]
            $resto = substr($linea, $mr[0][1] + strlen($mr[0][0]));
            preg_match_all('/(terminal|tpv|pin\s*pad)?\W*' . self::IP . '/iu', $resto, $mi, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            $kiosco = $terminal = null;
            foreach ($mi as $m) {
                $ip = $m[2][0];
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    continue;
                }
                if (($m[1][0] ?? '') !== '' && $m[1][1] >= 0) {
                    $terminal ??= $ip;
                } else {
                    $kiosco ??= ['ip' => $ip, 'pos' => $m[0][1]];
                }
            }
            if (!$kiosco) {
                $filas[] = ['linea' => $linea, 'recaudadora' => $reca, 'equipos' => [], 'error' => 'No trae la IP del kiosco.'];
                continue;
            }
            // Número de kiosco: un número suelto (1, 2…) antes de su IP
            $n = preg_match('/^\W*(\d{1,2})\b(?!\.)/', substr($resto, 0, $kiosco['pos']), $mn) ? (int) $mn[1] : null;
            $sufijo = $n ? " {$n}" : '';
            $sinTerminal = !$terminal && preg_match('/sin\s+ip\s+de\s+terminal|sin\s+terminal/iu', $resto);

            $equipos = [['tipo' => 'kiosco', 'nombre' => "Kiosco{$sufijo}", 'ip' => $kiosco['ip'],
                'notas' => $terminal ? "Su terminal de pago: {$terminal}" : ($sinTerminal ? 'Sin IP de terminal' : null)]];
            if ($terminal) {
                $equipos[] = ['tipo' => 'terminal', 'nombre' => "Terminal de pago{$sufijo}", 'ip' => $terminal, 'notas' => "Del Kiosco{$sufijo} ({$kiosco['ip']})"];
            }
            $filas[] = ['linea' => $linea, 'recaudadora' => $reca, 'equipos' => $equipos, 'error' => null];
        }
        return $filas;
    }

    /** Guarda lo interpretado. Una IP que la recaudadora ya tiene no se duplica. @return array{nuevos: int, existentes: int, avisos: list<string>} */
    public function guardar(array $filas): array
    {
        $res = ['nuevos' => 0, 'existentes' => 0, 'avisos' => []];
        foreach ($filas as $f) {
            if ($f['error']) {
                $res['avisos'][] = "«{$f['linea']}»: {$f['error']}";
                continue;
            }
            foreach ($f['equipos'] as $e) {
                $ya = $f['recaudadora']->equipos()->where('ip', $e['ip'])->first();
                if ($ya) {
                    $res['existentes']++;
                    $res['avisos'][] = "{$f['recaudadora']->titulo()}: {$e['ip']} ya estaba registrada como «{$ya->nombre}»; no se duplicó.";
                    continue;
                }
                $f['recaudadora']->equipos()->create($e);
                $res['nuevos']++;
            }
        }
        return $res;
    }
}
