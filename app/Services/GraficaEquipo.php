<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gráfica PNG (GD) de las últimas horas de un equipo para adjuntar a las alertas:
 * latencia (línea), pérdida de paquetes (barras) y franjas sin respuesta. Solo datos medidos.
 */
class GraficaEquipo
{
    private const ANCHO = 960;
    private const ALTO = 440;
    private const M_IZQ = 70, M_DER = 70, M_ARR = 70, M_ABA = 50;

    /** PNG en binario, o null si no hay GD o el equipo no tiene métricas en la ventana. */
    public function png(int $dispositivoId, ?Carbon $marca = null, int $horas = 3): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        $disp = DB::table('dispositivos')->where('id', $dispositivoId)->first(['nombre', 'ip']);
        if (!$disp) {
            return null;
        }

        $fin = now();
        $inicio = $fin->copy()->subHours($horas);
        $filas = DB::table('metricas_red')->where('dispositivo_id', $dispositivoId)
            ->where('fecha_registro', '>=', $inicio)->orderBy('fecha_registro')
            ->get(['fecha_registro', 'ping_ms', 'packet_loss']);
        if ($filas->isEmpty()) {
            return null;
        }

        // Promedio por minuto: la línea queda legible aunque el sondeo sea cada 15 s
        $minutos = [];
        foreach ($filas as $f) {
            $t = Carbon::parse($f->fecha_registro);
            $k = intdiv($t->timestamp, 60) * 60;
            $minutos[$k]['ping'][] = $f->ping_ms;
            $minutos[$k]['loss'][] = $f->packet_loss;
        }
        $puntos = [];
        foreach ($minutos as $ts => $m) {
            $pings = array_filter($m['ping'], fn ($v) => $v !== null);
            $loss = array_filter($m['loss'], fn ($v) => $v !== null);
            $puntos[] = [
                'ts' => $ts,
                'ping' => $pings ? array_sum($pings) / count($pings) : null,
                'loss' => $loss ? array_sum($loss) / count($loss) : null,
            ];
        }

        $img = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imageantialias($img, true);
        $c = fn ($hex, $alfa = 0) => imagecolorallocatealpha($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alfa);
        $fondo = $c('ffffff');
        $texto = $c('0f172a');
        $tenue = $c('64748b');
        $rejilla = $c('e2e8f0');
        $azul = $c('2563eb');
        $rojo = $c('dc2626', 70);
        $rojoFranja = $c('fecaca', 40);
        $ambar = $c('d97706');
        imagefilledrectangle($img, 0, 0, self::ANCHO, self::ALTO, $fondo);

        $x0 = self::M_IZQ;
        $x1 = self::ANCHO - self::M_DER;
        $y0 = self::M_ARR;
        $y1 = self::ALTO - self::M_ABA;
        $tsIni = $inicio->timestamp;
        $tsFin = $fin->timestamp;
        $x = fn ($ts) => $x0 + ($ts - $tsIni) / max(1, $tsFin - $tsIni) * ($x1 - $x0);

        $pingMax = max(1, ...array_map(fn ($p) => $p['ping'] ?? 0, $puntos));
        $escala = $this->escalaRedonda($pingMax * 1.15);
        $yPing = fn ($v) => $y1 - $v / $escala * ($y1 - $y0);
        $yLoss = fn ($v) => $y1 - $v / 100 * ($y1 - $y0);

        // Cada minuto se dibuja hasta la siguiente muestra si el hueco es corto (el ciclo de sondeo puede pasar de 60 s)
        foreach ($puntos as $i => $p) {
            $sig = $puntos[$i + 1]['ts'] ?? null;
            $puntos[$i]['hasta'] = $sig !== null && $sig - $p['ts'] <= 180 ? $sig : $p['ts'] + 60;
        }

        // Franjas sin respuesta (pérdida 100 %)
        foreach ($puntos as $p) {
            if ($p['loss'] !== null && $p['loss'] >= 100) {
                imagefilledrectangle($img, (int) $x($p['ts']), $y0, (int) $x($p['hasta']), $y1, $rojoFranja);
            }
        }

        // Rejilla y ejes
        for ($i = 0; $i <= 4; $i++) {
            $y = (int) ($y1 - $i / 4 * ($y1 - $y0));
            imageline($img, $x0, $y, $x1, $y, $rejilla);
            $this->texto($img, 9, $x0 - 8, $y + 4, $this->numero($escala * $i / 4) . ' ms', $azul, 'derecha');
            $this->texto($img, 9, $x1 + 8, $y + 4, ($i * 25) . ' %', $c('dc2626'));
        }
        $paso = $horas <= 3 ? 1800 : 3600;
        for ($ts = intdiv($tsIni, $paso) * $paso + $paso; $ts < $tsFin; $ts += $paso) {
            $xx = (int) $x($ts);
            imageline($img, $xx, $y0, $xx, $y1, $rejilla);
            $this->texto($img, 9, $xx, $y1 + 18, Carbon::createFromTimestamp($ts, config('app.timezone'))->format('H:i'), $tenue, 'centro');
        }

        // Pérdida de paquetes (barras)
        foreach ($puntos as $p) {
            if ($p['loss'] !== null && $p['loss'] > 0 && $p['loss'] < 100) {
                imagefilledrectangle($img, (int) $x($p['ts']), (int) $yLoss($p['loss']), (int) $x($p['ts'] + 60) - 1, $y1, $rojo);            }
        }

        // Latencia (línea; se corta donde no hubo respuesta)
        imagesetthickness($img, 2);
        $prev = null;
        foreach ($puntos as $p) {
            if ($p['ping'] === null) {
                $prev = null;
                continue;
            }
            $actual = [(int) $x($p['ts'] + 30), (int) $yPing($p['ping'])];
            if ($prev && $p['ts'] - $prev[2] <= 180) {
                imageline($img, $prev[0], $prev[1], $actual[0], $actual[1], $azul);
            }
            $prev = [...$actual, $p['ts']];
        }
        imagesetthickness($img, 1);

        // Inicio de la alarma
        if ($marca && $marca->timestamp > $tsIni) {
            $xm = (int) $x($marca->timestamp);
            imagesetthickness($img, 2);
            for ($y = $y0; $y < $y1; $y += 10) {
                imageline($img, $xm, $y, $xm, min($y1, $y + 5), $ambar);
            }
            imagesetthickness($img, 1);
            $this->texto($img, 9, $xm + 5, $y0 + 12, 'Inicio ' . $marca->copy()->setTimezone(config('app.timezone'))->format('H:i'), $ambar);
        }
        imagerectangle($img, $x0, $y0, $x1, $y1, $c('cbd5e1'));

        // Encabezado
        $this->texto($img, 15, $x0, 30, "{$disp->nombre}  ·  {$disp->ip}", $texto, 'izquierda', true);
        $ultimo = end($puntos);
        $resumen = 'Últimas ' . $horas . ' h · latencia actual: ' . ($ultimo['ping'] !== null ? $this->numero($ultimo['ping']) . ' ms' : 'sin respuesta')
            . ' · pérdida: ' . ($ultimo['loss'] !== null ? $this->numero($ultimo['loss']) . ' %' : 'sin dato');
        $this->texto($img, 10, $x0, 52, $resumen, $tenue);
        $this->texto($img, 10, $x1, 30, '— Latencia', $azul, 'derecha');
        $this->texto($img, 10, $x1, 48, '■ Pérdida / sin respuesta', $c('dc2626'), 'derecha');

        ob_start();
        imagepng($img, null, 6);
        imagedestroy($img);
        return ob_get_clean();
    }

    private function escalaRedonda(float $v): float
    {
        foreach ([2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000] as $e) {
            if ($v <= $e) {
                return $e;
            }
        }
        return ceil($v / 1000) * 1000;
    }

    private function numero(float $v): string
    {
        return $v >= 10 ? (string) round($v) : rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }

    private function fuente(bool $negrita): ?string
    {
        foreach ($negrita ? ['segoeuib.ttf', 'arialbd.ttf'] : ['segoeui.ttf', 'arial.ttf'] as $f) {
            foreach ([getenv('WINDIR') . '\\Fonts\\' . $f, '/usr/share/fonts/truetype/dejavu/DejaVuSans' . ($negrita ? '-Bold' : '') . '.ttf'] as $ruta) {
                if (is_file($ruta)) {
                    return $ruta;
                }
            }
        }
        return null;
    }

    private function texto($img, int $tam, float $x, float $y, string $txt, int $color, string $alinear = 'izquierda', bool $negrita = false): void
    {
        $fuente = function_exists('imagettftext') ? $this->fuente($negrita) : null;
        if (!$fuente) {
            $ancho = imagefontwidth(3) * strlen($txt);
            $dx = $alinear === 'derecha' ? -$ancho : ($alinear === 'centro' ? -$ancho / 2 : 0);
            imagestring($img, 3, (int) ($x + $dx), (int) ($y - 12), $txt, $color);
            return;
        }
        $caja = imagettfbbox($tam, 0, $fuente, $txt);
        $ancho = $caja[2] - $caja[0];
        $dx = $alinear === 'derecha' ? -$ancho : ($alinear === 'centro' ? -$ancho / 2 : 0);
        imagettftext($img, $tam, 0, (int) ($x + $dx), (int) $y, $color, $fuente, $txt);
    }
}
