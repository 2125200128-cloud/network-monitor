<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Imagen PNG (GD) con las tendencias de la última hora, como las del dashboard, para mandarla por Telegram
 * cada 2 horas (telegram:tendencias). Usa las mismas series que el dashboard (DashboardMetricsService::series('1h')).
 * Sin métricas viejas: una gráfica sin lecturas en los últimos MINUTOS_VIGENTE no dibuja lo anterior, lo dice.
 */
class ImagenTendencias
{
    public const MINUTOS_VIGENTE = 5;
    private const ANCHO = 1200;
    private const PANEL_ALTO = 310;
    private const ENCABEZADO = 92;
    private const M = 28;

    /** Paneles de cada grupo de Telegram. */
    public const GRUPOS = [
        'redes' => ['trafico', 'latencia', 'recursos', 'firewalls'],
        'servidores' => ['virtualizacion', 'web'],
    ];

    /**
     * @param array $datos   salida de DashboardMetricsService::series('1h')
     * @param string[] $paneles claves de panel (ver panel())
     * @return string|null PNG, o null si el servidor no tiene GD
     */
    public function png(array $datos, array $paneles, string $titulo, Carbon $desde, Carbon $hasta): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        $filas = (int) ceil(count($paneles) / 2);
        $alto = self::ENCABEZADO + $filas * (self::PANEL_ALTO + 16) + 24;
        $img = imagecreatetruecolor(self::ANCHO, $alto);
        imageantialias($img, true);
        $col = fn ($hex) => imagecolorallocate($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
        imagefilledrectangle($img, 0, 0, self::ANCHO, $alto, $col('eef1f5'));

        // Encabezado: qué es y la hora exacta que cubre
        $this->texto($img, 20, self::M, 44, $titulo, $col('1c273c'), true);
        $this->texto($img, 13, self::M, 72, 'Última hora: ' . $desde->format('H:i') . ' a ' . $hasta->format('H:i') . ' · ' . $hasta->locale('es')->isoFormat('dddd D [de] MMMM'), $col('637087'));
        $this->texto($img, 11, self::ANCHO - self::M, 44, 'NOC · Secretaría de la Hacienda Pública', $col('637087'), false, 'derecha');

        $anchoPanel = (self::ANCHO - 3 * self::M) / 2;
        foreach (array_values($paneles) as $i => $clave) {
            $x0 = self::M + ($i % 2) * ($anchoPanel + self::M);
            $y0 = self::ENCABEZADO + intdiv($i, 2) * (self::PANEL_ALTO + 16);
            $this->panel($img, $col, $this->definicion($clave, $datos), (int) $x0, (int) $y0, (int) $anchoPanel, self::PANEL_ALTO, $desde, $hasta);
        }

        ob_start();
        imagepng($img, null, 6);
        imagedestroy($img);
        return ob_get_clean();
    }

    /** Qué dibuja cada panel: título, series [nombre, color, tiempos (ms), valores, eje], formato del eje. */
    private function definicion(string $clave, array $d): array
    {
        $t = $d['t'] ?? [];
        $mbps = fn ($v) => $v >= 1000 ? $this->num($v / 1000) . ' Gbps' : $this->num($v) . ' Mbps';
        $pct = fn ($v) => $this->num($v) . ' %';
        return match ($clave) {
            'trafico' => ['titulo' => 'Red · Tráfico (Σ puertos)', 'fmt' => $mbps, 'series' => [
                ['Entrada', '3b5998', $t, $d['trafico_in'] ?? []], ['Salida', 'f26419', $t, $d['trafico_out'] ?? []]]],
            'latencia' => ['titulo' => 'Red · Latencia y pérdida', 'fmt' => fn ($v) => $this->num($v) . ' ms', 'fmt2' => $pct, 'series' => [
                ['Latencia', '5c8096', $t, $d['latencia'] ?? []], ['Pérdida', 'ef4444', $t, $d['perdida'] ?? [], 'der']]],
            'recursos' => ['titulo' => 'Red · CPU / RAM de los equipos', 'fmt' => $pct, 'max' => 100, 'series' => [
                ['CPU', 'f26419', $t, $d['cpu'] ?? []], ['RAM', '3b5998', $t, $d['ram'] ?? []]]],
            'firewalls' => ['titulo' => 'Firewalls · Sesiones activas', 'fmt' => fn ($v) => number_format($v), 'series' => [
                ['Sesiones', 'f26419', $d['fw_t'] ?? [], $d['fw_sesiones'] ?? []]]],
            'virtualizacion' => ['titulo' => 'Virtualización · Hosts ESXi', 'fmt' => $pct, 'max' => 100, 'series' => [
                ['CPU', 'f26419', $d['virt_t'] ?? [], $d['virt_cpu'] ?? []], ['Memoria', '3b5998', $d['virt_t'] ?? [], $d['virt_ram'] ?? []]]],
            'web' => ['titulo' => 'Servicios web · Tiempo de respuesta', 'fmt' => fn ($v) => number_format($v) . ' ms', 'series' => [
                ['Promedio', '5c8096', $d['web_t'] ?? [], $d['web_ms'] ?? []]]],
        };
    }

    private function panel($img, callable $col, array $def, int $x0, int $y0, int $w, int $h, Carbon $desde, Carbon $hasta): void
    {
        $this->rectRedondo($img, $x0, $y0, $x0 + $w, $y0 + $h, 12, $col('ffffff'));
        $texto = $col('1c273c');
        $tenue = $col('637087');
        $rejilla = $col('e4e8ee');
        $this->texto($img, 13, $x0 + 16, $y0 + 28, $def['titulo'], $texto, true);

        $ini = $desde->getTimestampMs();
        $fin = $hasta->getTimestampMs();
        // Puntos de la ventana; la serie cuenta como vigente si su última lectura es reciente
        $series = [];
        $ultima = null;
        foreach ($def['series'] as $s) {
            [$nombre, $color, $tiempos, $valores] = $s;
            $pts = [];
            foreach ($tiempos as $i => $ms) {
                if ($ms >= $ini && $ms <= $fin && isset($valores[$i])) {
                    $pts[] = [$ms, (float) $valores[$i]];
                }
            }
            $ultima = max($ultima ?? 0, $pts ? end($pts)[0] : 0);
            $series[] = ['nombre' => $nombre, 'color' => $col($color), 'pts' => $pts, 'eje' => $s[4] ?? 'izq'];
        }

        $gx0 = $x0 + 74;
        $gx1 = $x0 + $w - (isset($def['fmt2']) ? 64 : 20);
        $gy0 = $y0 + 52;
        $gy1 = $y0 + $h - 34;

        if (!$ultima || $ultima < $fin - self::MINUTOS_VIGENTE * 60000) {
            $msg = $ultima ? 'Sin datos recientes · última lectura ' . Carbon::createFromTimestampMs($ultima, config('app.timezone'))->format('H:i') : 'Sin lecturas en la última hora';
            $this->texto($img, 13, ($gx0 + $gx1) / 2, ($gy0 + $gy1) / 2, $msg, $tenue, true, 'centro');
            return;
        }

        // Valores actuales en el encabezado del panel, con el color de su línea
        $xLeyenda = $x0 + $w - 16;
        foreach (array_reverse($series) as $s) {
            if (!$s['pts']) {
                continue;
            }
            $fmt = $s['eje'] === 'der' ? $def['fmt2'] : $def['fmt'];
            $etiqueta = $s['nombre'] . ' ' . $fmt(end($s['pts'])[1]);
            $caja = $this->medir(11, $etiqueta, true);
            $this->texto($img, 11, $xLeyenda, $y0 + 27, $etiqueta, $s['color'], true, 'derecha');
            $xLeyenda -= $caja + 18;
        }

        // Escalas (la de la derecha solo si hay serie en ese eje)
        $maxIzq = $def['max'] ?? $this->escala(max(1e-9, ...array_merge([0], ...array_map(fn ($s) => $s['eje'] === 'izq' ? array_column($s['pts'], 1) : [], $series)))) ;
        $maxDer = isset($def['fmt2']) ? $this->escala(max(1, ...array_merge([0], ...array_map(fn ($s) => $s['eje'] === 'der' ? array_column($s['pts'], 1) : [], $series)))) : null;
        for ($i = 0; $i <= 4; $i++) {
            $y = (int) ($gy1 - $i / 4 * ($gy1 - $gy0));
            imageline($img, $gx0, $y, $gx1, $y, $rejilla);
            $this->texto($img, 10, $gx0 - 8, $y + 4, ($def['fmt'])($maxIzq * $i / 4), $tenue, false, 'derecha');
            if ($maxDer !== null) {
                $this->texto($img, 10, $gx1 + 8, $y + 4, ($def['fmt2'])($maxDer * $i / 4), $col('ef4444'));
            }
        }
        // Horas cada 15 min
        $x = fn ($ms) => $gx0 + ($ms - $ini) / max(1, $fin - $ini) * ($gx1 - $gx0);
        for ($m = (int) (ceil($ini / 900000) * 900000); $m <= $fin; $m += 900000) {
            // Carbon crea desde un timestamp en UTC: la hora se muestra en la zona de la aplicación
            $this->texto($img, 10, $x($m), $gy1 + 20, Carbon::createFromTimestampMs($m, config('app.timezone'))->format('H:i'), $tenue, false, 'centro');
        }

        // Líneas (se cortan en huecos de más de 3 min: sin lectura no se dibuja nada)
        imagesetthickness($img, 2);
        foreach ($series as $s) {
            $max = $s['eje'] === 'der' ? $maxDer : $maxIzq;
            $prev = null;
            foreach ($s['pts'] as [$ms, $v]) {
                $p = [(int) $x($ms), (int) ($gy1 - min(1, $v / $max) * ($gy1 - $gy0)), $ms];
                if ($prev && $ms - $prev[2] <= 180000) {
                    imageline($img, $prev[0], $prev[1], $p[0], $p[1], $s['color']);
                }
                $prev = $p;
            }
        }
        imagesetthickness($img, 1);
    }

    private function escala(float $v): float
    {
        $v *= 1.15;
        $base = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $f) {
            if ($v <= $f * $base) {
                return $f * $base;
            }
        }
        return 10 * $base;
    }

    private function num(float $v): string
    {
        return $v >= 100 ? number_format($v) : rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }

    private function rectRedondo($img, int $x0, int $y0, int $x1, int $y1, int $r, int $color): void
    {
        imagefilledrectangle($img, $x0 + $r, $y0, $x1 - $r, $y1, $color);
        imagefilledrectangle($img, $x0, $y0 + $r, $x1, $y1 - $r, $color);
        foreach ([[$x0 + $r, $y0 + $r], [$x1 - $r, $y0 + $r], [$x0 + $r, $y1 - $r], [$x1 - $r, $y1 - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }

    private function fuente(bool $negrita): ?string
    {
        if (!function_exists('imagettftext')) {
            return null;
        }
        foreach ($negrita ? ['segoeuib.ttf', 'arialbd.ttf'] : ['segoeui.ttf', 'arial.ttf'] as $f) {
            foreach ([getenv('WINDIR') . '\\Fonts\\' . $f, '/usr/share/fonts/truetype/dejavu/DejaVuSans' . ($negrita ? '-Bold' : '') . '.ttf'] as $ruta) {
                if (is_file($ruta)) {
                    return $ruta;
                }
            }
        }
        return null;
    }

    private function medir(int $tam, string $txt, bool $negrita): int
    {
        $fuente = $this->fuente($negrita);
        return $fuente ? imagettfbbox($tam, 0, $fuente, $txt)[2] : imagefontwidth(4) * strlen($txt);
    }

    private function texto($img, int $tam, float $x, float $y, string $txt, int $color, bool $negrita = false, string $alinear = 'izquierda'): void
    {
        $ancho = $this->medir($tam, $txt, $negrita);
        $dx = $alinear === 'derecha' ? -$ancho : ($alinear === 'centro' ? -$ancho / 2 : 0);
        $fuente = $this->fuente($negrita);
        if (!$fuente) {
            imagestring($img, 4, (int) ($x + $dx), (int) ($y - 12), $txt, $color);
            return;
        }
        imagettftext($img, $tam, 0, (int) ($x + $dx), (int) $y, $color, $fuente, $txt);
    }
}
