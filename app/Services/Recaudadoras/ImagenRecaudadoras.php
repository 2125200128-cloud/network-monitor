<?php

namespace App\Services\Recaudadoras;

use App\Console\Commands\RevisarRecaudadorasCommand;
use App\Models\Recaudadora;
use App\Services\Concerns\DibujoGd;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Imagen PNG (GD) del aviso de recaudadoras: a la izquierda el mapa de Jalisco con todas las recaudadoras en el color
 * de su estado y las del aviso resaltadas con su número; a la derecha la ficha de cada una (estado y desde cuándo,
 * IPs, enlace y la referencia para el reporte con el proveedor). Va por Telegram y dentro del correo.
 * El mapa es el mismo archivo de municipios (INEGI) y la misma proyección que la página Recaudadoras.
 */
class ImagenRecaudadoras
{
    use DibujoGd;

    private const ANCHO = 1280;
    private const ALTO = 820;
    private const S = 2;                       // se dibuja al doble y se reduce al final: bordes suaves
    private const MAPA = [24, 80, 724, 772];   // x0, y0, x1, y1
    private const PANEL = [748, 80, 1256, 786];
    private const R = 4.7;                     // radio de un punto (px), como en la página: ancho del mapa / 150
    private const ORDEN = ['online' => 0, 'sin_ip' => 1, 'pendiente' => 2, 'lan' => 3, 'offline' => 4];

    private static ?array $municipios = null;

    /** Escala del dibujo (todas las medidas se escriben en px de la imagen final). */
    private function p(float $v): int
    {
        return (int) round($v * self::S);
    }

    /**
     * @param Recaudadora[] $caidas sin comunicación en este aviso
     * @param Recaudadora[] $lan    con falla de red interna en este aviso
     * @param Collection|null $todas las recaudadoras activas para el mapa (por omisión, de la base)
     * @return string|null PNG, o null si no hay nada que mostrar o el servidor no tiene GD
     */
    public function png(array $caidas, array $lan, ?Collection $todas = null): ?string
    {
        if (!function_exists('imagecreatetruecolor') || (!$caidas && !$lan) || !($mun = self::municipios())) {
            return null;
        }
        $todas ??= Recaudadora::where('activo', true)->get();

        // Las del aviso, con la etiqueta que las une en el mapa y en su ficha (su número; sin número, una letra)
        $afectadas = [];
        $letra = 'A';
        foreach ([...array_map(fn ($r) => [$r, 'offline'], $caidas), ...array_map(fn ($r) => [$r, 'lan'], $lan)] as [$r, $estado]) {
            $afectadas[$r->id] = ['r' => $r, 'estado' => $estado,
                'etiqueta' => ($r->tipo === 'recaudadora' && (string) $r->numero !== '') ? (string) $r->numero : $letra++];
        }

        $img = imagecreatetruecolor($this->p(self::ANCHO), $this->p(self::ALTO));
        $c = fn (string $hex, int $alfa = 0) => imagecolorallocatealpha($img, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)), $alfa);
        $blanco = $c('#ffffff');
        $texto = $c('#1c273c');
        $tenue = $c('#637087');
        $borde = $c('#d8dee8');
        imagefilledrectangle($img, 0, 0, $this->p(self::ANCHO), $this->p(self::ALTO), $blanco);

        // ---------- Encabezado ----------
        $nCaidas = count($caidas);
        $nLan = count($lan);
        $acento = $nCaidas ? Recaudadora::ESTADOS['offline']['color'] : Recaudadora::ESTADOS['lan']['color'];
        imagefilledrectangle($img, 0, 0, $this->p(self::ANCHO), $this->p(58), $c($acento));
        $titulo = implode('  ·  ', array_filter([
            $nCaidas ? ($nCaidas === 1 ? 'SIN COMUNICACIÓN' : "{$nCaidas} SIN COMUNICACIÓN") : null,
            $nLan ? ($nLan === 1 ? 'FALLA DE RED INTERNA' : "{$nLan} CON FALLA DE RED INTERNA") : null,
        ]));
        $this->texto($img, $this->p(16), $this->p(24), $this->p(37), 'RECAUDADORAS  ·  ' . $titulo, $blanco, true);
        $this->texto($img, $this->p(12), $this->p(self::ANCHO - 24), $this->p(36), now()->format('d/m/Y H:i'), $blanco, false, 'derecha');

        // ---------- Mapa ----------
        [$mx0, $my0, $mx1, $my1] = self::MAPA;
        [$bx0, $by0, $bx1, $by1] = $mun['caja'];
        $esc = min(($mx1 - $mx0) / ($bx1 - $bx0), ($my1 - $my0) / ($by1 - $by0));
        $ox = $mx0 + (($mx1 - $mx0) - ($bx1 - $bx0) * $esc) / 2;
        $oy = $my0 + (($my1 - $my0) - ($by1 - $by0) * $esc) / 2;
        $px = fn (array $pt) => [$ox + ($pt[0] - $bx0) * $esc, $oy + ($pt[1] - $by0) * $esc];

        $conReca = $todas->pluck('municipio_cve')->filter()->flip();
        $fondoMun = $c('#eef1f5');
        $fondoConReca = $c('#e1e7ee');
        $lineaMun = $c('#c3ccd8');
        imagesetthickness($img, self::S);
        foreach ($mun['lista'] as $m) {
            foreach ($m['anillos'] as $anillo) {
                if (count($anillo) < 3) {
                    continue;
                }
                $puntos = [];
                foreach ($anillo as $pt) {
                    [$x, $y] = $px($pt);
                    $puntos[] = $this->p($x);
                    $puntos[] = $this->p($y);
                }
                imagefilledpolygon($img, $puntos, isset($conReca[$m['cve']]) ? $fondoConReca : $fondoMun);
                imagepolygon($img, $puntos, $lineaMun);
            }
        }
        imagesetthickness($img, 1);

        // Posición de cada recaudadora: la cabecera de su municipio; varias en uno, en espiral alrededor (como la página)
        $pos = [];
        foreach ($todas->filter(fn ($r) => $r->municipio_cve && isset($mun['cabeceras'][$r->municipio_cve]))
                     ->sortBy([['tipo', 'asc'], ['id', 'asc']])->groupBy('municipio_cve') as $cve => $grupo) {
            [$cx, $cy] = $px($mun['cabeceras'][$cve]);
            foreach ($grupo->values() as $i => $r) {
                $ang = $i * 2.4;
                $dist = $i === 0 ? 0 : 1.15 * sqrt($i);
                $pos[$r->id] = [$cx + cos($ang) * $dist * self::R * 2.3, $cy + sin($ang) * $dist * self::R * 2.3];
            }
        }

        // Puntos: los demás primero (las caídas encima) y al final las del aviso, más grandes y con su etiqueta
        $estadoDe = fn (Recaudadora $r) => $afectadas[$r->id]['estado'] ?? $r->estadoVisible();
        $normales = $todas->filter(fn ($r) => isset($pos[$r->id]) && !isset($afectadas[$r->id]))
            ->sortBy(fn ($r) => self::ORDEN[$estadoDe($r)] ?? 0);
        foreach ($normales as $r) {
            [$x, $y] = $pos[$r->id];
            $rad = $r->tipo === 'oficina' ? self::R * 0.75 : self::R;
            imagefilledellipse($img, $this->p($x), $this->p($y), $this->p(($rad + 1.2) * 2), $this->p(($rad + 1.2) * 2), $blanco);
            imagefilledellipse($img, $this->p($x), $this->p($y), $this->p($rad * 2), $this->p($rad * 2), $c(Recaudadora::ESTADOS[$estadoDe($r)]['color']));
        }
        foreach ($afectadas as $id => $a) {
            if (isset($pos[$id])) {
                [$x, $y] = $pos[$id];
                $color = Recaudadora::ESTADOS[$a['estado']]['color'];
                imagefilledellipse($img, $this->p($x), $this->p($y), $this->p(self::R * 6), $this->p(self::R * 6), $c($color, 95));
                imagefilledellipse($img, $this->p($x), $this->p($y), $this->p(self::R * 3.6), $this->p(self::R * 3.6), $blanco);
                imagefilledellipse($img, $this->p($x), $this->p($y), $this->p(self::R * 2.6), $this->p(self::R * 2.6), $c($color));
            }
        }
        // Las etiquetas al final, para que ningún punto las tape
        $ocupado = [];
        foreach ($afectadas as $id => $a) {
            if (isset($pos[$id])) {
                $this->etiquetaMapa($img, $a['etiqueta'], $pos[$id][0], $pos[$id][1], $c(Recaudadora::ESTADOS[$a['estado']]['color']), $blanco, $ocupado);
            }
        }

        // Leyenda con el conteo actual de todas
        $conteo = $todas->countBy(fn ($r) => $estadoDe($r));
        $x = $mx0 + 4;
        foreach ([['online', 'En servicio'], ['lan', 'Falla de red interna'], ['offline', 'Sin comunicación'], ['pendiente', 'Sin revisar']] as [$e, $nombre]) {
            $n = $conteo[$e] ?? 0;
            if ($e === 'pendiente') {
                $n += $conteo['sin_ip'] ?? 0;
            }
            imagefilledellipse($img, $this->p($x + 6), $this->p(self::ALTO - 30), $this->p(11), $this->p(11), $c(Recaudadora::ESTADOS[$e]['color']));
            $txt = "{$nombre} {$n}";
            $this->texto($img, $this->p(10.5), $this->p($x + 16), $this->p(self::ALTO - 26), $txt, $texto);
            $x += 16 + $this->anchoTexto($txt, $this->p(10.5), false) / self::S + 22;
        }

        // ---------- Fichas ----------
        [$fx0, $fy0, $fx1, $fy1] = self::PANEL;
        $y = $fy0;
        if (count($caidas) >= RevisarRecaudadorasCommand::CAIDA_MASIVA) {
            $lineas = $this->partir(count($caidas) . ' recaudadoras perdieron comunicación a la vez: probable falla general del enlace del proveedor (MPLS) o del core, no de cada oficina.',
                $this->p(11.5), true, $this->p($fx1 - $fx0 - 28));
            $alto = 20 + count($lineas) * 19;
            $this->rectRedondo($img, $this->p($fx0), $this->p($y), $this->p($fx1), $this->p($y + $alto), $this->p(8), $c('#fde8e8'));
            foreach ($lineas as $i => $l) {
                $this->texto($img, $this->p(11.5), $this->p($fx0 + 14), $this->p($y + 25 + $i * 19), $l, $c('#b91c1c'), true);
            }
            $y += $alto + 10;
        }

        // Con pocas, la ficha completa; con más, compacta para que quepan
        $lista = array_values($afectadas);
        $modo = count($lista) <= 3 ? 'completa' : (count($lista) < RevisarRecaudadorasCommand::CAIDA_MASIVA ? 'compacta' : 'minima');
        foreach ($lista as $i => $a) {
            $lineas = $this->lineasFicha($a, $modo);
            $alto = 16 + 22 + count($lineas) * 19 + 8;
            $quedan = count($lista) - $i - 1;
            if ($y + $alto + ($quedan ? 46 : 0) > $fy1) {
                $resto = array_slice($lista, $i);
                $txt = 'Y ' . count($resto) . ' más: ' . implode(', ', array_map(fn ($a) => $a['r']->titulo(), $resto));
                $renglones = $this->partir($txt, $this->p(11), false, $this->p($fx1 - $fx0));
                if (count($renglones) > 2) {   // no cabe todo: el segundo renglón termina en «…»
                    $renglones[1] = $this->recortarA($renglones[1] . ' …', $this->p(11), false, $this->p($fx1 - $fx0));
                }
                foreach (array_slice($renglones, 0, 2) as $k => $l) {
                    $this->texto($img, $this->p(11), $this->p($fx0), $this->p($y + 16 + $k * 18), $l, $tenue);
                }
                break;
            }
            $color = Recaudadora::ESTADOS[$a['estado']]['color'];
            $this->rectRedondo($img, $this->p($fx0), $this->p($y), $this->p($fx1), $this->p($y + $alto), $this->p(8), $borde);
            $this->rectRedondo($img, $this->p($fx0 + 1), $this->p($y + 1), $this->p($fx1 - 1), $this->p($y + $alto - 1), $this->p(7), $blanco);
            imagefilledrectangle($img, $this->p($fx0 + 1), $this->p($y + 6), $this->p($fx0 + 5), $this->p($y + $alto - 6), $c($color));

            // Etiqueta (la misma del mapa) y nombre
            $ancho = $this->anchoTexto($a['etiqueta'], $this->p(10.5), true) / self::S + 14;
            $this->rectRedondo($img, $this->p($fx0 + 16), $this->p($y + 13), $this->p($fx0 + 16 + $ancho), $this->p($y + 33), $this->p(5), $c($color));
            $this->texto($img, $this->p(10.5), $this->p($fx0 + 16 + $ancho / 2), $this->p($y + 28), $a['etiqueta'], $blanco, true, 'centro');
            $nombre = $this->recortarA($a['r']->titulo(), $this->p(13), true, $this->p($fx1 - $fx0 - $ancho - 40));
            $this->texto($img, $this->p(13), $this->p($fx0 + 26 + $ancho), $this->p($y + 30), $nombre, $texto, true);

            $yy = $y + 38;
            foreach ($lineas as [$l, $tipo]) {
                $yy += 19;
                $colorLinea = match ($tipo) { 'estado' => $c($color), 'mal' => $c(Recaudadora::ESTADOS['offline']['color']), 'bien' => $c(Recaudadora::ESTADOS['online']['color']), default => $tenue };
                $this->texto($img, $this->p(11), $this->p($fx0 + 16), $this->p($yy), $this->recortarA($l, $this->p(11), $tipo === 'estado', $this->p($fx1 - $fx0 - 30)), $colorLinea, $tipo === 'estado');
            }
            $y += $alto + 10;
        }

        $this->texto($img, $this->p(9.5), $this->p(self::ANCHO - 24), $this->p(self::ALTO - 12), 'NOC · Secretaría de la Hacienda Pública', $tenue, false, 'derecha');

        // Se reduce a su tamaño final
        $final = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imagecopyresampled($final, $img, 0, 0, 0, 0, self::ANCHO, self::ALTO, $this->p(self::ANCHO), $this->p(self::ALTO));
        imagedestroy($img);
        ob_start();
        imagepng($final, null, 6);
        imagedestroy($final);
        return ob_get_clean();
    }

    /**
     * Renglones de la ficha: [texto, tipo] con tipo estado | mal | bien | dato.
     * completa: estado, lugar, cada IP, enlace y referencia · compacta: estado, IPs y enlace con referencia · minima: estado y enlace.
     */
    private function lineasFicha(array $a, string $modo = 'completa'): array
    {
        /** @var Recaudadora $r */
        $r = $a['r'];
        $dato = fn ($v) => ($v = trim((string) $v)) === '' || in_array(mb_strtoupper($v), ['N/A', 'NA', 'N/D', '-', '--', 'SIN DATO', 'PENDIENTE', 'FALTANTE'], true) ? null : $v;
        $desde = $r->estado_desde ? Carbon::parse($r->estado_desde) : null;
        $lineas = [[Recaudadora::ESTADOS[$a['estado']]['etiqueta']
            . ($desde ? ' desde ' . $desde->format($desde->isToday() ? 'H:i' : 'd/m H:i') . ' · ' . RevisarRecaudadorasCommand::duracion(max(60, (int) $desde->diffInSeconds(now(), true))) : ''), 'estado']];

        $enlace = implode(' · ', array_filter([$dato($r->tipo_enlace), $dato($r->uninet) ? 'Uninet ' . $dato($r->uninet) : null,
            $dato($r->velocidad_red_jalisco) ? 'Red Jalisco ' . $dato($r->velocidad_red_jalisco) : null]));
        $ref = $dato($r->referencia) ?? $dato($r->numero_referencia) ?? ($dato($r->id_sitio_red_jalisco) ? 'ID sitio ' . $dato($r->id_sitio_red_jalisco) : null);

        if ($modo === 'completa') {
            $coord = $dato($r->coordinadora);
            $lugar = implode(' · ', array_filter([$dato($r->municipio), $coord && ctype_digit($coord) ? "Coordinadora {$coord}" : $coord,
                $r->municipio_cve ? null : 'sin ubicación en el mapa']));
            if ($lugar !== '') {
                $lineas[] = [$lugar, 'dato'];
            }
            foreach ([['IP administrativa', $r->ip_administrativa, $r->admin_ok], ['Loopback', $r->ip_loopback, $r->loopback_ok]] as [$nombre, $ip, $ok]) {
                if ($ip) {
                    $lineas[] = ["{$nombre} {$ip} · " . ($ok === null ? 'sin dato' : ($ok ? 'responde' : 'no responde')), $ok ? 'bien' : ($ok === null ? 'dato' : 'mal')];
                }
            }
            if ($enlace !== '') {
                $lineas[] = ["Enlace: {$enlace}", 'dato'];
            }
            if ($ref) {
                $lineas[] = ["Referencia: {$ref}", 'dato'];
            }
            return $lineas;
        }

        if ($modo === 'compacta') {
            $ips = array_filter([$r->ip_administrativa ? "admin {$r->ip_administrativa}" : null, $r->ip_loopback ? "loopback {$r->ip_loopback}" : null]);
            if ($ips) {
                $lineas[] = $a['estado'] === 'lan' && $r->loopback_ok
                    ? ["Responde la loopback {$r->ip_loopback}; la IP admin {$r->ip_administrativa} no", 'mal']
                    : ['No responden: ' . implode(', ', $ips), 'mal'];
            }
        }
        $linea = implode(' · ', array_filter([$enlace ?: null, $ref ? "Ref. {$ref}" : null]));
        if ($linea !== '') {
            $lineas[] = [$linea, 'dato'];
        }
        return $lineas;
    }

    /** Etiqueta con el número junto al punto; si choca con otra, prueba más arriba o más abajo. */
    private function etiquetaMapa($img, string $txt, float $x, float $y, int $fondo, int $letra, array &$ocupado): void
    {
        $w = $this->anchoTexto($txt, $this->p(10), true) / self::S + 12;
        $h = 18;
        $lado = $x + 14 + $w > self::MAPA[2] ? -1 : 1;
        foreach ([0, -20, 20, -40, 40, -60, 60] as $dy) {
            $x0 = $lado > 0 ? $x + 12 : $x - 12 - $w;
            $y0 = $y - $h / 2 + $dy;
            $choca = false;
            foreach ($ocupado as [$a0, $b0, $a1, $b1]) {
                if ($x0 < $a1 && $x0 + $w > $a0 && $y0 < $b1 && $y0 + $h > $b0) {
                    $choca = true;
                    break;
                }
            }
            if (!$choca) {
                break;
            }
        }
        $ocupado[] = [$x0, $y0, $x0 + $w, $y0 + $h];
        if ($dy !== 0) {   // si se movió, una línea la une con su punto
            imagesetthickness($img, self::S);
            imageline($img, $this->p($x), $this->p($y), $this->p($lado > 0 ? $x0 : $x0 + $w), $this->p($y0 + $h / 2), $fondo);
            imagesetthickness($img, 1);
        }
        $this->rectRedondo($img, $this->p($x0), $this->p($y0), $this->p($x0 + $w), $this->p($y0 + $h), $this->p(5), $fondo);
        $this->texto($img, $this->p(10), $this->p($x0 + $w / 2), $this->p($y0 + 13.5), $txt, $letra, true, 'centro');
    }

    /** Municipios proyectados como en la página (equirectangular corregida por latitud), con su caja y cabeceras. */
    private static function municipios(): ?array
    {
        if (self::$municipios !== null) {
            return self::$municipios ?: null;
        }
        $datos = json_decode((string) @file_get_contents(public_path('data/jalisco-municipios.json')), true);
        if (empty($datos['municipios'])) {
            return (self::$municipios = []) ?: null;
        }
        $lats = [];
        foreach ($datos['municipios'] as $m) {
            foreach ($m['anillos'] as $a) {
                foreach ($a as $p) {
                    $lats[] = $p[1];
                }
            }
        }
        $k = cos(deg2rad((min($lats) + max($lats)) / 2));
        $proy = fn ($lon, $lat) => [$lon * $k * 100, -$lat * 100];
        $lista = [];
        $cabeceras = [];
        $xs = $ys = [];
        foreach ($datos['municipios'] as $m) {
            $anillos = [];
            foreach ($m['anillos'] as $a) {
                $anillos[] = array_map(function ($p) use ($proy, &$xs, &$ys) {
                    $q = $proy($p[0], $p[1]);
                    $xs[] = $q[0];
                    $ys[] = $q[1];
                    return $q;
                }, $a);
            }
            $lista[] = ['cve' => $m['cve'], 'anillos' => $anillos];
            $cabeceras[$m['cve']] = $proy($m['cabecera']['lon'], $m['cabecera']['lat']);
        }
        return self::$municipios = ['lista' => $lista, 'cabeceras' => $cabeceras, 'caja' => [min($xs), min($ys), max($xs), max($ys)]];
    }
}
