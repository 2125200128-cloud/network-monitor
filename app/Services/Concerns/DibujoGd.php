<?php

namespace App\Services\Concerns;

/**
 * Ayudantes de dibujo con GD para las imágenes que acompañan a los avisos (texto con fuente TrueType, líneas que
 * quepan en un ancho, recuadros redondeados). Sin fuente TrueType en el servidor se usa la fuente interna de GD.
 */
trait DibujoGd
{
    /** Parte el texto en líneas que quepan en el ancho dado (px). */
    private function partir(string $txt, float $tam, bool $negrita, int $ancho): array
    {
        $lineas = [];
        $actual = '';
        foreach (preg_split('/\s+/', trim($txt)) as $palabra) {
            $prueba = $actual === '' ? $palabra : "{$actual} {$palabra}";
            if ($this->anchoTexto($prueba, $tam, $negrita) > $ancho && $actual !== '') {
                $lineas[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $prueba;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }
        return $lineas;
    }

    /** El texto recortado con «…» para que quepa en el ancho dado (px). */
    private function recortarA(string $txt, float $tam, bool $negrita, int $ancho): string
    {
        if ($this->anchoTexto($txt, $tam, $negrita) <= $ancho) {
            return $txt;
        }
        while (mb_strlen($txt) > 1 && $this->anchoTexto(rtrim($txt, ' ,·') . '…', $tam, $negrita) > $ancho) {
            $txt = mb_substr($txt, 0, -1);
        }
        return rtrim($txt, ' ,·') . '…';
    }

    private function anchoTexto(string $txt, float $tam, bool $negrita): int
    {
        $fuente = $this->fuente($negrita);
        return $fuente ? (int) ((imagettfbbox($tam, 0, $fuente, $txt)[2] ?? 0) - (imagettfbbox($tam, 0, $fuente, $txt)[0] ?? 0))
            : strlen($txt) * imagefontwidth(5);
    }

    private function rectRedondo($img, int $x0, int $y0, int $x1, int $y1, int $r, int $color): void
    {
        $r = min($r, intdiv($y1 - $y0, 2), intdiv($x1 - $x0, 2));
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

    private function texto($img, float $tam, float $x, float $y, string $txt, int $color, bool $negrita = false, string $alinear = 'izquierda'): void
    {
        $fuente = $this->fuente($negrita);
        if (!$fuente) {
            $ancho = imagefontwidth(5) * strlen($txt);
            $dx = $alinear === 'derecha' ? -$ancho : ($alinear === 'centro' ? -$ancho / 2 : 0);
            imagestring($img, 5, (int) ($x + $dx), (int) ($y - 14), $txt, $color);
            return;
        }
        $caja = imagettfbbox($tam, 0, $fuente, $txt);
        $ancho = $caja[2] - $caja[0];
        $dx = $alinear === 'derecha' ? -$ancho : ($alinear === 'centro' ? -$ancho / 2 : 0);
        imagettftext($img, $tam, 0, (int) ($x + $dx), (int) $y, $color, $fuente, $txt);
    }
}
