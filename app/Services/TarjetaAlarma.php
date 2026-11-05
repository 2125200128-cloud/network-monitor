<?php

namespace App\Services;

use App\Models\AlarmaRed;
use App\Models\ConfiguracionGeneral;
use Carbon\Carbon;

/**
 * Tarjeta PNG (GD) que acompaña a cada aviso de Telegram: severidad, qué pasa, el equipo y el dato clave de la
 * alarma contra su umbral (temperatura, pérdida, CPU, memoria, espacio…) o desde cuándo está caído.
 * Solo usa lo que ya trae la alarma y los umbrales con que se generó: nada se estima.
 */
class TarjetaAlarma
{
    private const ANCHO = 960;
    private const ALTO = 400;
    private const M = 44;   // margen lateral

    // Alarmas de "no responde": el dato clave es desde cuándo
    private const CAIDAS = ['HOST_UNREACHABLE', 'HOST_UNREACHABLE_LINK_UP', 'CASCADE_FAILURE', 'PHYSICAL_DISCONNECT',
        'WEB_SERVICE_DOWN', 'ESXI_VM_POWERED_OFF', 'ESXI_HOST_DISCONNECTED', 'ESXI_DATASTORE_INACCESSIBLE'];

    /** PNG en binario, o null si el servidor no tiene GD. */
    public function png(AlarmaRed $a): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        $img = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imageantialias($img, true);
        $c = fn ($hex) => imagecolorallocate($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
        $critica = $a->severidad === 'critica';
        $acento = $c($critica ? 'c8262d' : 'b45f06');
        $acentoSuave = $c($critica ? 'fde8e8' : 'fdf1e3');
        $blanco = $c('ffffff');
        $texto = $c('1c273c');
        $tenue = $c('637087');
        $borde = $c('d8dee8');
        $fondoBarra = $c('e9edf3');
        imagefilledrectangle($img, 0, 0, self::ANCHO, self::ALTO, $blanco);

        // Franja de severidad con el tiempo que lleva activa
        imagefilledrectangle($img, 0, 0, self::ANCHO, 58, $acento);
        $this->texto($img, 17, self::M, 38, $critica ? 'CRÍTICA' : 'ADVERTENCIA', $blanco, true);
        $inicio = $a->fecha_inicio ? Carbon::parse($a->fecha_inicio) : null;
        if ($inicio) {
            $this->texto($img, 12, self::ANCHO - self::M, 36, 'Desde ' . $inicio->format('d/m/Y H:i'), $blanco, false, 'derecha');
        }

        // Qué pasa (hasta 2 líneas)
        $y = 104;
        foreach (array_slice($this->partir($a->titulo, 21, true, self::ANCHO - 2 * self::M), 0, 2) as $linea) {
            $this->texto($img, 21, self::M, $y, $linea, $texto, true);
            $y += 34;
        }

        // Equipo
        $d = $a->dispositivo;
        if ($d) {
            $this->texto($img, 14, self::M, $y + 6, $d->nombre . ($d->ip ? '  ·  ' . $d->ip : ''), $texto, true);
            $detalle = collect([$d->ubicacion, $d->tipo_label])->filter()->implode('  ·  ');
            $this->texto($img, 12, self::M, $y + 30, $detalle, $tenue);
            $y += 30;
        }

        // Dato clave dentro de un recuadro
        $y0 = max($y + 30, 236);
        $this->rectRedondo($img, self::M, $y0, self::ANCHO - self::M, self::ALTO - 34, 12, $acentoSuave);
        $dato = $this->datoClave($a);
        $xi = self::M + 24;
        if ($dato) {
            $this->texto($img, 12, $xi, $y0 + 32, mb_strtoupper($dato['etiqueta']), $tenue, true);
            $this->texto($img, 34, $xi, $y0 + 82, $dato['valor'], $acento, true);
            if (isset($dato['nota'])) {
                $this->texto($img, 12, self::ANCHO - self::M - 24, $y0 + 32, $dato['nota'], $tenue, false, 'derecha');
            }
            if (isset($dato['pct'])) {
                // Barra del valor en su escala, con la marca del umbral
                $bx0 = $xi + 250;
                $bx1 = self::ANCHO - self::M - 24;
                $by = $y0 + 62;
                $this->rectRedondo($img, $bx0, $by, $bx1, $by + 16, 8, $fondoBarra);
                $relleno = $bx0 + max(16, min(1, $dato['pct']) * ($bx1 - $bx0));
                $this->rectRedondo($img, $bx0, $by, (int) $relleno, $by + 16, 8, $acento);
                if (isset($dato['umbral_pct'])) {
                    $xu = (int) ($bx0 + $dato['umbral_pct'] * ($bx1 - $bx0));
                    imagefilledrectangle($img, $xu - 1, $by - 8, $xu + 1, $by + 24, $texto);
                    $this->texto($img, 11, $xu, $by + 42, $dato['umbral_texto'], $texto, true, 'centro');
                }
            }
        } else {
            // Sin dato numérico: lo que reportó el equipo (hasta 3 líneas)
            $this->texto($img, 12, $xi, $y0 + 32, 'DETALLE', $tenue, true);
            $yy = $y0 + 62;
            foreach (array_slice($this->partir((string) $a->mensaje, 15, false, self::ANCHO - 2 * self::M - 48), 0, 3) as $linea) {
                $this->texto($img, 15, $xi, $yy, $linea, $texto);
                $yy += 26;
            }
        }
        imagerectangle($img, 0, 0, self::ANCHO - 1, self::ALTO - 1, $borde);
        $this->texto($img, 10, self::ANCHO - self::M, self::ALTO - 12, 'NOC · Secretaría de la Hacienda Pública', $tenue, false, 'derecha');

        ob_start();
        imagepng($img, null, 6);
        imagedestroy($img);
        return ob_get_clean();
    }

    /**
     * Dato clave según el tipo de alarma: [etiqueta, valor, nota?, pct (0–1 en su escala)?, umbral_pct?, umbral_texto?].
     * El valor sale del título o mensaje que armó el diagnóstico; los umbrales, de las mismas constantes que lo generaron.
     */
    private function datoClave(AlarmaRed $a): ?array
    {
        $D = AlarmDiagnosisService::class;
        $num = fn (string $patron, ?string $txt = null) => preg_match($patron, $txt ?? $a->titulo, $m) ? (float) $m[1] : null;
        $barra = function (string $etiqueta, float $v, string $unidad, float $aviso, float $escala) {
            $fmt = fn ($x) => rtrim(rtrim(number_format($x, 1, '.', ''), '0'), '.');
            return ['etiqueta' => $etiqueta, 'valor' => $fmt($v) . " {$unidad}", 'pct' => $v / $escala,
                'umbral_pct' => $aviso / $escala, 'umbral_texto' => "Umbral {$fmt($aviso)} {$unidad}"];
        };

        // Falla intermitente de un sitio (también si la alarma empezó como caída y la clave se conservó)
        if ($a->codigo_evento === 'WEB_SERVICE_INTERMITTENT' || str_starts_with((string) $a->titulo, 'Falla intermitente')) {
            $v = $num('/(\d+) % de las peticiones/');
            if ($v !== null) {
                return $barra('Peticiones con error', $v, '%', WebServicePoller::ERROR_ENTRA_INTERMITENTE * 100, 100);
            }
        }

        switch ($a->codigo_evento) {
            case 'TEMP_CRITICAL':
                $v = $num('/([\d.]+)\s*°C/');
                return $v === null ? null : $barra('Temperatura del chasis', $v, '°C', $D::TEMP_AVISO, max(80, $v + 10));
            case 'PACKET_LOSS_HIGH':
                $v = $num('/([\d.]+)\s*%/');
                $umbral = (float) (ConfiguracionGeneral::query()->value('umbral_loss_warning') ?? 5);
                return $v === null ? null : $barra('Pérdida de paquetes', $v, '%', $umbral, 100);
            case 'ESXI_HOST_CPU_HIGH':
                $v = $num('/al ([\d.]+)%/');
                return $v === null ? null : $barra('CPU del host', $v, '%', $D::ESXI_CPU_AVISO, 100);
            case 'ESXI_HOST_MEMORY_HIGH':
                $v = $num('/al ([\d.]+)%/');
                return $v === null ? null : $barra('Memoria del host', $v, '%', $D::ESXI_RAM_AVISO, 100);
            case 'ESXI_DATASTORE_FULL':
                $v = $num('/al ([\d.]+)%/');
                return $v === null ? null : $barra('Espacio usado', $v, '%', $D::DATASTORE_AVISO, 100);
            case 'ESXI_VM_CPU_HIGH':
                $v = $num('/hace (\d+) min/');
                return $v === null ? null : $barra('Minutos con CPU ≥ 90 %', $v, 'min', $D::VM_CPU_MINUTOS, max(90, $v + 15));
            case 'SSL_EXPIRING':
                $v = $num('/en (\d+) días/');
                return $v === null ? null : ['etiqueta' => 'Días para que venza', 'valor' => (int) $v . ' días'];
        }
        if (in_array($a->codigo_evento, self::CAIDAS, true) && $a->fecha_inicio) {
            $inicio = Carbon::parse($a->fecha_inicio);
            return ['etiqueta' => $a->codigo_evento === 'WEB_SERVICE_DOWN' ? 'Sitio caído desde' : 'Sin respuesta desde',
                'valor' => $inicio->format('H:i'), 'nota' => $inicio->locale('es')->isoFormat('dddd D [de] MMMM')];
        }
        return null;
    }

    /** Parte el texto en líneas que quepan en el ancho dado (px). */
    private function partir(string $txt, int $tam, bool $negrita, int $ancho): array
    {
        $fuente = $this->fuente($negrita);
        $lineas = [];
        $actual = '';
        foreach (preg_split('/\s+/', trim($txt)) as $palabra) {
            $prueba = $actual === '' ? $palabra : "{$actual} {$palabra}";
            $w = $fuente ? (imagettfbbox($tam, 0, $fuente, $prueba)[2] ?? 0) : strlen($prueba) * imagefontwidth(3);
            if ($w > $ancho && $actual !== '') {
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

    private function texto($img, int $tam, float $x, float $y, string $txt, int $color, bool $negrita = false, string $alinear = 'izquierda'): void
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
