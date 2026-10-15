<?php

namespace App\Services;

use App\Models\Dispositivo;

/**
 * Ilustración del panel frontal según el modelo real reportado por el equipo (modelo_detectado / sysDescr).
 *
 * Interpreta el número de parte (p.ej. C9300L-48P-4X → 48 RJ45 PoE + 4 SFP+, N9K-C93180YC-EX → 48 SFP + 6 QSFP,
 * C9407R → chasis de 7 ranuras) y dibuja un SVG propio con la familia, la cantidad de puertos y los colores del
 * fabricante. Los SVG se generan una sola vez en public/images/topology/modelos y se reutilizan.
 *
 * Los LEDs se dibujan neutros a propósito: la imagen es la misma para todos los equipos del modelo y no debe
 * aparentar un estado; el estado real lo muestra la topología.
 */
class DeviceArtwork
{
    /** Subir al cambiar el diseño: genera archivos nuevos y evita la caché del navegador. */
    public const VERSION = 2;
    public const DIRECTORIO = 'images/topology/modelos';

    /** Ancho en el lienzo de la topología (vis-network) según el tipo de dibujo. */
    private const ANCHO_LIENZO = ['rack' => 1450, 'modular' => 760, 'escritorio' => 760, 'ap' => 260, 'radio' => 300];

    private const TEMAS = [
        'cisco9k' => ['contorno' => '#8a94a3', 'c0' => '#40464f', 'c1' => '#262a30', 'c2' => '#17191d', 'borde' => '#0b0c0e', 'texto' => '#f3f4f6', 'sub' => '#9ca3af', 'acento' => '#00bceb', 'puerto' => '#0a0b0d', 'marca' => 'cisco'],
        'ciscoClasico' => ['contorno' => '#8a94a3', 'c0' => '#56606d', 'c1' => '#3d4652', 'c2' => '#2b323b', 'borde' => '#1a1f25', 'texto' => '#f3f4f6', 'sub' => '#b6c0cc', 'acento' => '#8fb3dc', 'puerto' => '#0d0f12', 'marca' => 'cisco'],
        'nexus' => ['contorno' => '#8a94a3', 'c0' => '#33373c', 'c1' => '#1f2226', 'c2' => '#111315', 'borde' => '#050506', 'texto' => '#f3f4f6', 'sub' => '#9ca3af', 'acento' => '#00bceb', 'puerto' => '#060708', 'marca' => 'cisco'],
        'ciscoSmb' => ['contorno' => '#8a94a3', 'c0' => '#4b5058', 'c1' => '#34383e', 'c2' => '#24272b', 'borde' => '#141618', 'texto' => '#f3f4f6', 'sub' => '#a8adb5', 'acento' => '#9ca3af', 'puerto' => '#0b0c0e', 'marca' => 'cisco'],
        'isr' => ['contorno' => '#4b5563', 'c0' => '#8a929c', 'c1' => '#6c747e', 'c2' => '#545b64', 'borde' => '#363b41', 'texto' => '#ffffff', 'sub' => '#e5e7eb', 'acento' => '#00bceb', 'puerto' => '#121417', 'marca' => 'cisco'],
        'huawei' => ['contorno' => '#4b5563', 'c0' => '#e4e7eb', 'c1' => '#cdd2d9', 'c2' => '#b4bac3', 'borde' => '#8a919b', 'texto' => '#1f2937', 'sub' => '#4b5563', 'acento' => '#e60012', 'puerto' => '#15171a', 'marca' => 'HUAWEI'],
        'forti' => ['contorno' => '#8a94a3', 'c0' => '#36363a', 'c1' => '#232326', 'c2' => '#151517', 'borde' => '#070708', 'texto' => '#f3f4f6', 'sub' => '#a1a1aa', 'acento' => '#da291c', 'puerto' => '#08080a', 'marca' => 'FORTINET'],
        'arista' => ['contorno' => '#8a94a3', 'c0' => '#3a3e44', 'c1' => '#26292e', 'c2' => '#17191c', 'borde' => '#08090a', 'texto' => '#f3f4f6', 'sub' => '#9ca3af', 'acento' => '#2a7de1', 'puerto' => '#08090a', 'marca' => 'ARISTA'],
        'generico' => ['contorno' => '#8a94a3', 'c0' => '#4b5563', 'c1' => '#374151', 'c2' => '#1f2937', 'borde' => '#111827', 'texto' => '#f3f4f6', 'sub' => '#9ca3af', 'acento' => '#64748b', 'puerto' => '#0b0d10', 'marca' => ''],
    ];

    /**
     * @return array{image: string, size: int, serie: string}|null  null si el modelo no se reconoce
     *         (se conserva la imagen genérica del tipo).
     */
    public static function paraDispositivo(Dispositivo $d): ?array
    {
        $texto = trim(($d->modelo_detectado ?? '') . ' ' . ($d->telemetriaChasis->model_name ?? ''));
        if ($texto === '' || !self::especificacion($texto)) {
            // Último recurso: equipos sin SNMP cuyo nombre trae el número de parte (p.ej. "Cisco SG200-26 Smart Switch")
            $texto = trim($texto . ' ' . $d->nombre);
        }
        $spec = $texto !== '' ? self::especificacion($texto) : null;
        return $spec ? self::asegurarArchivo($spec) : null;
    }

    /** Genera (si no existe) el SVG de la especificación y devuelve ruta pública, tamaño en el lienzo y serie. */
    public static function asegurarArchivo(array $spec, bool $forzar = false): array
    {
        $relativa = self::DIRECTORIO . '/' . $spec['clave'] . '.v' . self::VERSION . '.svg';
        $ruta = public_path($relativa);
        if ($forzar || !is_file($ruta)) {
            if (!is_dir(dirname($ruta))) {
                @mkdir(dirname($ruta), 0775, true);
            }
            @file_put_contents($ruta, self::svg($spec));
        }

        [$w, $h] = self::dimensiones($spec);
        $ancho = self::ANCHO_LIENZO[$spec['dibujo']];
        // vis-network: el lado corto mide 2·size
        $size = $w >= $h ? $ancho / (2 * $w / $h) : $ancho / 2;

        return ['image' => $relativa, 'size' => (int) round($size), 'serie' => $spec['serie']];
    }

    // =====================================================================
    //  Interpretación del número de parte
    // =====================================================================
    public static function especificacion(string $texto): ?array
    {
        $t = strtoupper(preg_replace('/\s+/', ' ', $texto));
        $base = fn (array $extra) => $extra + ['u' => 1, 'rj45' => 0, 'sfp' => 0, 'qsfp' => 0, 'poe' => false, 'nim' => 0,
            'filasRj45' => 2, 'ranuras' => 0, 'sups' => [], 'modelo' => ''];

        // ---- Radioenlaces InfiNet (WANFleX) ----
        if (preg_match('/WANFLEX|INFINET/', $t)) {
            $serie = preg_match('/\b(E5000|XG|R5000|H\d{2}S\d{2})\b/', $t, $m) ? 'InfiNet ' . $m[1] : 'InfiNet';
            return $base(['clave' => 'infinet-' . self::slug($serie), 'dibujo' => 'radio', 'tema' => 'generico', 'marca' => 'InfiNet', 'serie' => $serie]);
        }

        // ---- Access points ----
        if (preg_match('/\b(C9\d{3}AX[A-Z]*)|AIR-C?AP\w+|OAW-AP\d+|FORTIAP-\w+/', $t, $m)) {
            $modelo = rtrim($m[0], '-');
            $marca = str_starts_with($modelo, 'OAW') ? 'Alcatel-Lucent' : (str_starts_with($modelo, 'FORTIAP') ? 'FORTINET' : 'cisco');
            return $base(['clave' => 'ap-' . self::slug($modelo), 'dibujo' => 'ap', 'tema' => 'generico', 'marca' => $marca,
                'serie' => str_starts_with($modelo, 'C91') ? 'Catalyst ' . substr($modelo, 1, 4) : $modelo, 'modelo' => $modelo]);
        }

        // ---- Controlador inalámbrico Catalyst 9800 ----
        if (preg_match('/C9800-(\w+)/', $t, $m)) {
            return $base(['clave' => 'cisco-c9800-' . strtolower($m[1]), 'dibujo' => 'rack', 'tema' => 'cisco9k', 'marca' => 'cisco',
                'serie' => 'Catalyst 9800-' . $m[1], 'modelo' => 'Wireless Controller', 'rj45' => 2, 'filasRj45' => 1, 'sfp' => 4]);
        }

        // ---- FortiGate / FortiSwitch ----
        if (preg_match('/FORTIGATE-(\d+)([A-Z])/', $t, $m)) {
            $n = (int) $m[1];
            $serie = 'FortiGate ' . $n . $m[2];
            if ($n < 100) {
                return $base(['clave' => 'fortigate-' . $n . strtolower($m[2]), 'dibujo' => 'escritorio', 'tema' => 'forti', 'marca' => 'FORTINET', 'serie' => $serie, 'rj45' => 8, 'sfp' => 2]);
            }
            [$u, $rj, $sfp, $q] = match (true) {
                $n >= 1000 => [3, 2, 32, 4],
                $n >= 400 => [1, 10, 16, 0],
                $n >= 200 => [1, 18, 12, 0],
                default => [1, 22, 6, 0],
            };
            return $base(['clave' => 'fortigate-' . $n . strtolower($m[2]), 'dibujo' => 'rack', 'tema' => 'forti', 'marca' => 'FORTINET', 'serie' => $serie,
                'u' => $u, 'rj45' => $rj, 'sfp' => $sfp, 'qsfp' => $q]);
        }
        if (preg_match('/FORTISWITCH-(\d+)([A-Z])/', $t, $m)) {
            return $base(['clave' => 'fortiswitch-' . $m[1] . strtolower($m[2]), 'dibujo' => 'rack', 'tema' => 'forti', 'marca' => 'FORTINET',
                'serie' => 'FortiSwitch ' . $m[1] . $m[2], 'sfp' => 48, 'qsfp' => 6]);
        }

        // ---- Nexus modular 7000 ----
        if (preg_match('/N7K-C70(\d{2})|\bN7000\b|N7000-/', $t, $m)) {
            $ranuras = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 9;
            $ranuras = $ranuras === 4 ? 4 : min(10, max(4, $ranuras));
            $sups = $ranuras === 4 ? [1, 2] : [intdiv($ranuras, 2), intdiv($ranuras, 2) + 1];
            return $base(['clave' => 'nexus-7k-' . $ranuras, 'dibujo' => 'modular', 'tema' => 'nexus', 'marca' => 'cisco',
                'serie' => 'Nexus 70' . str_pad((string) $ranuras, 2, '0', STR_PAD_LEFT), 'ranuras' => $ranuras, 'sups' => $sups]);
        }

        // ---- Nexus 9000 / 3000 de rack (ToR) ----
        if (preg_match('/N9K-C(9\d{3,4}[A-Z0-9-]*)|N3K-C(3\d{3,4}[A-Z0-9-]*)/', $t, $m)) {
            $modelo = $m[1] !== '' ? $m[1] : $m[2];
            $cobre = str_contains($modelo, 'TX') || str_contains($modelo, 'TC');
            $qsfp = preg_match('/93240|9364|9332/', $modelo) ? 12 : 6;
            $serie = 'Nexus ' . preg_replace('/^(\d+[A-Z]*).*/', '$1', $modelo);
            return $base(['clave' => 'nexus-' . self::slug($modelo), 'dibujo' => 'rack', 'tema' => 'nexus', 'marca' => 'cisco', 'serie' => $serie,
                'modelo' => ($m[1] !== '' ? 'N9K-C' : 'N3K-C') . $modelo, 'rj45' => $cobre ? 48 : 0, 'sfp' => $cobre ? 0 : 48, 'qsfp' => $qsfp]);
        }
        if (preg_match('/NX-OS.*\b(N3000|N9000|NXOS)\b/', $t)) {
            return $base(['clave' => 'nexus-generico', 'dibujo' => 'rack', 'tema' => 'nexus', 'marca' => 'cisco', 'serie' => 'Nexus', 'sfp' => 48, 'qsfp' => 6]);
        }

        // ---- Catalyst modulares (9400 / 9600 / 6800) ----
        if (preg_match('/\bC(94\d{2}|96\d{2}|68\d{2})R?(-XL)?\b/', $t, $m)) {
            $familia = $m[1];
            $ranuras = (int) substr($familia, -2) ?: 7;
            $ranuras = min(10, max(4, $ranuras));
            $sups = [intdiv($ranuras + 1, 2), intdiv($ranuras + 1, 2) + 1];
            return $base(['clave' => 'catalyst-' . strtolower($familia), 'dibujo' => 'modular', 'tema' => str_starts_with($familia, '68') ? 'ciscoClasico' : 'cisco9k',
                'marca' => 'cisco', 'serie' => 'Catalyst ' . $familia . (str_starts_with($familia, '68') ? '-XL' : 'R'), 'ranuras' => $ranuras, 'sups' => $sups]);
        }

        // ---- Catalyst 9500 ----
        if (preg_match('/C9500-(\d+)([YQXC])(\d*)([A-Z]?)/', $t, $m)) {
            $n = (int) $m[1];
            return $base(['clave' => 'catalyst-9500-' . strtolower($m[0]), 'dibujo' => 'rack', 'tema' => 'cisco9k', 'marca' => 'cisco',
                'serie' => 'Catalyst 9500', 'modelo' => $m[0], 'sfp' => $m[2] === 'Q' ? 0 : $n, 'qsfp' => $m[2] === 'Q' ? $n : ((int) $m[3] ?: 4)]);
        }

        // ---- Routers ----
        if (preg_match('/ISR(4\d{3}|1\d{3})/', $t, $m)) {
            $grande = (int) $m[1] >= 4400;
            return $base(['clave' => 'isr-' . $m[1], 'dibujo' => 'rack', 'tema' => 'isr', 'marca' => 'cisco', 'serie' => 'ISR ' . $m[1],
                'u' => $grande ? 2 : 1, 'nim' => $grande ? 3 : 2, 'rj45' => $grande ? 4 : 3, 'filasRj45' => 1, 'sfp' => $grande ? 4 : 2]);
        }
        if (preg_match('/CISCO\s?(18\d{2}|19\d{2}|28\d{2}|29\d{2})|C(1841|2811|2911)/', $t, $m)) {
            $modelo = $m[1] !== '' ? $m[1] : $m[2];
            return $base(['clave' => 'cisco-' . $modelo, 'dibujo' => 'rack', 'tema' => 'ciscoClasico', 'marca' => 'cisco', 'serie' => 'Cisco ' . $modelo,
                'nim' => 2, 'rj45' => 2, 'filasRj45' => 1]);
        }
        if (preg_match('/NETENGINE\s?8000\s?(\w+)?|NE8000/', $t, $m)) {
            return $base(['clave' => 'huawei-ne8000', 'dibujo' => 'rack', 'tema' => 'huawei', 'marca' => 'HUAWEI',
                'serie' => 'NetEngine 8000' . (!empty($m[1]) ? ' ' . $m[1] : ''), 'sfp' => 12, 'rj45' => 4, 'filasRj45' => 1]);
        }

        // ---- Switches de acceso Cisco ----
        if (preg_match('/\b(C9300L?|C9200L?|C1000|WS-C(?:2960[A-Z]{0,2}|3560[A-Z0-9]{0,2}|3750[A-Z0-9]{0,2}|3850)|SG\d{3}|SG\d{3}X?|SRW\d{4}P?)-?(\d{2})([A-Z]*)(?:-(\d)([XGC]))?/', $t, $m)) {
            return $base(self::switchCisco($m[1], (int) $m[2], $m[3] ?? '', isset($m[4]) && $m[4] !== '' ? (int) $m[4] : null, $m[5] ?? '', $m[0]));
        }

        // ---- Huawei ----
        if (preg_match('/\bS(\d{3,4})-([A-Z]*)(\d{2})([A-Z]*?)(\d)?([XGC])?(-[A-Z0-9]+)?\b/', $t, $m) && str_contains($t, 'HUAWEI')) {
            $n = (int) $m[3];
            $soloSfp = str_starts_with($m[4], 'S');
            return $base(['clave' => 'huawei-s' . strtolower($m[1] . '-' . $m[2] . $m[3] . $m[4] . ($m[5] ?? '') . ($m[6] ?? '')), 'dibujo' => 'rack', 'tema' => 'huawei',
                'marca' => 'HUAWEI', 'serie' => 'S' . $m[1], 'modelo' => trim($m[0], '-'),
                'rj45' => $soloSfp ? 0 : $n, 'sfp' => ($soloSfp ? $n : 0) + ((int) ($m[5] ?? 0) ?: 4), 'poe' => str_contains($m[4], 'P')]);
        }
        if (str_contains($t, 'HUAWEI') && preg_match('/\(S(\d{3,4})\b/', $t, $m)) {
            return $base(['clave' => 'huawei-s' . $m[1], 'dibujo' => 'rack', 'tema' => 'huawei', 'marca' => 'HUAWEI', 'serie' => 'S' . $m[1], 'rj45' => 24, 'sfp' => 4]);
        }

        // ---- Arista ----
        if (preg_match('/DCS-(7\d{3}[A-Z]*)-(\d+)C(\d+)/', $t, $m)) {
            return $base(['clave' => 'arista-' . strtolower($m[1] . '-' . $m[2] . 'c' . $m[3]), 'dibujo' => 'rack', 'tema' => 'arista', 'marca' => 'ARISTA',
                'serie' => $m[1] . '-' . $m[2] . 'C' . $m[3], 'sfp' => (int) $m[2], 'qsfp' => (int) $m[3]]);
        }

        // ---- Ciena Packetwave (chasis) ----
        if (preg_match('/PACKETWAVE|CIENA/', $t)) {
            $ranuras = preg_match('/(\d+)-SLOT/', $t, $m) ? min(10, (int) $m[1]) : 10;
            return $base(['clave' => 'ciena-packetwave-' . $ranuras, 'dibujo' => 'modular', 'tema' => 'generico', 'marca' => 'Ciena',
                'serie' => 'Packetwave 8700', 'ranuras' => $ranuras, 'sups' => [1, 2]]);
        }

        return null;
    }

    /** Switches de acceso Cisco: puertos, uplinks y PoE a partir del número de parte. */
    private static function switchCisco(string $familia, int $n, string $sufijo, ?int $nUplink, string $tipoUplink, string $modelo): array
    {
        $tema = match (true) {
            str_starts_with($familia, 'C9') => 'cisco9k',
            str_starts_with($familia, 'SG') || str_starts_with($familia, 'SRW') => 'ciscoSmb',
            default => 'ciscoClasico',
        };
        $serie = match (true) {
            str_starts_with($familia, 'C9300') => 'Catalyst 9300' . (str_ends_with($familia, 'L') ? 'L' : ''),
            str_starts_with($familia, 'C9200') => 'Catalyst 9200' . (str_ends_with($familia, 'L') ? 'L' : ''),
            $familia === 'C1000' => 'Catalyst 1000',
            str_starts_with($familia, 'WS-C') => 'Catalyst ' . preg_replace('/^(\d{4})(.*)$/', '$1$2', substr($familia, 4)),
            default => $familia,
        };
        $serie = str_replace(['2960X', '2960S', '2960L', '2960G', '3750X', '3560G', '3560V2'], ['2960-X', '2960-S', '2960-L', '2960G', '3750-X', '3560G', '3560 V2'], $serie);

        // Small Business: 26/28/50/52 = 24/48 + combo/SFP
        $rj45 = $n;
        $sfp = 0;
        if (in_array($n, [26, 28, 50, 52], true)) {
            $rj45 = $n >= 50 ? 48 : 24;
            $sfp = $n - $rj45;
        } elseif ($nUplink !== null) {
            $sfp = $nUplink;
        } elseif (str_contains($sufijo, 'TT')) {
            $rj45 += 2;
        } elseif (str_contains($sufijo, 'D')) {
            $sfp = 2;
        } elseif (preg_match('/^WS-C(3560|3750)$/', $familia) && $n === 24) {
            $sfp = 2;
        } else {
            $sfp = $tipoUplink === '' && str_starts_with($familia, 'WS-C2960') && !preg_match('/2960[XSLG]/', $familia) ? 2 : 4;
        }

        return [
            'clave' => 'cisco-' . self::slug($familia . '-' . $n . $sufijo . ($nUplink ? '-' . $nUplink . $tipoUplink : '')),
            'dibujo' => 'rack', 'tema' => $tema, 'marca' => 'cisco', 'serie' => $serie, 'modelo' => rtrim($modelo, '-'),
            'rj45' => $rj45, 'sfp' => $sfp, 'poe' => (bool) preg_match('/P/', $sufijo),
        ];
    }

    // =====================================================================
    //  Dibujo
    // =====================================================================
    public static function dimensiones(array $s): array
    {
        return match ($s['dibujo']) {
            'rack' => [480, 16 + 48 * $s['u']],
            'modular' => [400, 46 + $s['ranuras'] * 30 + 58],
            'escritorio' => [320, 84],
            'ap' => [240, 240],
            'radio' => [200, 260],
        };
    }

    public static function svg(array $s): string
    {
        [$w, $h] = self::dimensiones($s);
        $t = self::TEMAS[$s['tema']] ?? self::TEMAS['generico'];
        $cuerpo = match ($s['dibujo']) {
            'rack' => self::rack($s, $t, $w, $h),
            'modular' => self::modular($s, $t, $w, $h),
            'escritorio' => self::escritorio($s, $t, $w, $h),
            'ap' => self::ap($s, $w),
            'radio' => self::radio($s, $w, $h),
        };

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '">'
            . '<defs>'
            . '<linearGradient id="cuerpo" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $t['c0'] . '"/><stop offset="0.45" stop-color="' . $t['c1'] . '"/><stop offset="1" stop-color="' . $t['c2'] . '"/></linearGradient>'
            . '<linearGradient id="oreja" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#8d96a3"/><stop offset="0.5" stop-color="#d3d8df"/><stop offset="1" stop-color="#7b8490"/></linearGradient>'
            . '<linearGradient id="jaula" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e2e6eb"/><stop offset="0.55" stop-color="#9aa3ae"/><stop offset="1" stop-color="#6b7480"/></linearGradient>'
            . '<linearGradient id="blanco" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#dfe3e8"/></linearGradient>'
            . '<pattern id="rejilla" width="4" height="4" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="0.9" fill="' . $t['borde'] . '" opacity="0.85"/></pattern>'
            . '<filter id="sombra" x="-5%" y="-10%" width="110%" height="135%"><feDropShadow dx="0" dy="2.5" stdDeviation="2.5" flood-color="#0f172a" flood-opacity="0.35"/></filter>'
            . '</defs>'
            . '<g filter="url(#sombra)">' . $cuerpo . '</g></svg>';
    }

    /** Equipo de rack 1U/2U/3U: orejas, zona de marca, bloques de puertos y rejilla de ventilación. */
    private static function rack(array $s, array $t, int $w, int $h): string
    {
        $o = self::orejas($w, $h)
            . sprintf('<rect x="14" y="3" width="%d" height="%d" rx="3" fill="url(#cuerpo)" stroke="%s" stroke-width="1.6"/>', $w - 28, $h - 6, $t['contorno'])
            . sprintf('<rect x="15" y="4" width="%d" height="1.2" fill="#ffffff" opacity="0.18"/>', $w - 30);

        // Zona de marca
        $o .= sprintf('<rect x="20" y="9" width="3" height="%d" rx="1.5" fill="%s"/>', $h - 18, $t['acento']);
        $o .= self::marca($t, $s, 28, $h);

        // Bloques de puertos, escalados para caber entre la zona de marca y la oreja derecha
        $bloques = [];
        if ($s['nim'] > 0) {
            $bloques[] = ['nim', $s['nim']];
        }
        if ($s['rj45'] > 0) {
            $bloques[] = ['rj45', $s['rj45'], $s['filasRj45']];
        }
        if ($s['sfp'] > 0) {
            $bloques[] = ['sfp', $s['sfp'], $s['sfp'] >= 4 ? 2 : 1];
        }
        if ($s['qsfp'] > 0) {
            $bloques[] = ['qsfp', $s['qsfp'], $s['qsfp'] >= 4 ? 2 : 1];
        }
        $x0 = 112;
        $x1 = $w - 22;
        $o .= self::bloquesPuertos($bloques, $t, $x0, $x1, $h);

        return $o;
    }

    /** Dibuja bloques [tipo, cantidad, filas] centrados verticalmente; agrega rejilla si sobra espacio. */
    private static function bloquesPuertos(array $bloques, array $t, float $x0, float $x1, int $h): string
    {
        $medidas = ['rj45' => [10.5, 9, 1.6, 6], 'sfp' => [12, 8.5, 1.8, 8], 'qsfp' => [17, 10, 2.2, 0], 'nim' => [64, 20, 6, 0]];
        $separacion = 10;
        $natural = 0;
        $calc = [];
        foreach ($bloques as $b) {
            [$tipo, $n] = $b;
            $filas = $b[2] ?? ($tipo === 'nim' ? ($h > 70 ? 2 : 1) : 1);
            [$pw, $ph, $gap, $grupo] = $medidas[$tipo];
            $cols = (int) ceil($n / $filas);
            $grupos = $grupo > 0 ? (int) ceil($cols / $grupo) - 1 : 0;
            $ancho = $cols * $pw + ($cols - 1) * $gap + $grupos * 5;
            $calc[] = compact('tipo', 'n', 'filas', 'pw', 'ph', 'gap', 'grupo', 'cols', 'ancho');
            $natural += $ancho;
        }
        $natural += $separacion * max(0, count($calc) - 1);
        $disponible = $x1 - $x0;
        $k = $natural > $disponible ? $disponible / $natural : 1;

        $o = '';
        $x = $x0;
        foreach ($calc as $c) {
            $pw = $c['pw'] * $k;
            $gap = $c['gap'] * $k;
            $altoFilas = $c['filas'] * $c['ph'] + ($c['filas'] - 1) * ($c['tipo'] === 'nim' ? 6 : 3.5);
            $y0 = ($h - $altoFilas) / 2;
            for ($i = 0; $i < $c['n']; $i++) {
                $col = intdiv($i, $c['filas']);
                $fila = $i % $c['filas'];
                $px = $x + $col * ($pw + $gap) + ($c['grupo'] > 0 ? intdiv($col, $c['grupo']) * 5 * $k : 0);
                $py = $y0 + $fila * ($c['ph'] + ($c['tipo'] === 'nim' ? 6 : 3.5));
                $o .= self::puerto($c['tipo'], $px, $py, $pw, $c['ph'], $t, $fila === 0);
            }
            $x += $c['ancho'] * $k + $separacion * $k;
        }

        // Rejilla de ventilación en el espacio que sobra
        $resto = $x1 - ($x - $separacion * $k);
        if ($resto > 24 && $h >= 40) {
            $o .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2" fill="url(#rejilla)"/>', $x1 - $resto + 8, 12, $resto - 10, $h - 24);
        }
        return $o;
    }

    private static function puerto(string $tipo, float $x, float $y, float $w, float $h, array $t, bool $filaSuperior): string
    {
        return match ($tipo) {
            'rj45' => sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="0.8" fill="%s" stroke="#000" stroke-opacity="0.5" stroke-width="0.4"/>', $x, $y, $w, $h, $t['puerto'])
                . sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="2" fill="#2f353d"/>', $x + $w * 0.3, $filaSuperior ? $y + $h - 2 : $y, $w * 0.4)
                . sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="0.7" fill="#c9a43b" opacity="0.8"/>', $x + 1.5, $filaSuperior ? $y + 1.6 : $y + $h - 2.3, $w - 3),
            'sfp', 'qsfp' => sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="0.6" fill="url(#jaula)" stroke="#4b5563" stroke-width="0.4"/>', $x, $y, $w, $h)
                . sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="0.4" fill="#0b0d10"/>', $x + 1.3, $y + 1.6, $w - 2.6, $h - 3.2),
            'nim' => sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="1.5" fill="%s" opacity="0.55" stroke="%s" stroke-width="0.8"/>', $x, $y, $w, $h, $t['c2'], $t['borde'])
                . sprintf('<circle cx="%.2f" cy="%.2f" r="1.6" fill="#c7ccd3"/><circle cx="%.2f" cy="%.2f" r="1.6" fill="#c7ccd3"/>', $x + 4, $y + $h / 2, $x + $w - 4, $y + $h / 2)
                . sprintf('<text x="%.2f" y="%.2f" font-family="Segoe UI, Arial, sans-serif" font-size="6" fill="%s" text-anchor="middle" opacity="0.8">NIM</text>', $x + $w / 2, $y + $h / 2 + 2, $t['sub']),
        };
    }

    /** Marca, serie, modelo y LEDs neutros. */
    private static function marca(array $t, array $s, float $x, float $h, float $y0 = 0): string
    {
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_XML1);
        $marca = $s['marca'] ?? $t['marca'];
        $cy = $y0 + $h / 2;
        $o = '';
        if ($marca !== '') {
            $esCisco = $marca === 'cisco';
            $o .= sprintf('<text x="%.1f" y="%.1f" font-family="Segoe UI, Arial, sans-serif" font-size="%s" font-weight="%s" letter-spacing="%s" fill="%s">%s</text>',
                $x, $cy - 6, $esCisco ? 12 : 9.5, $esCisco ? 600 : 800, $esCisco ? 0.3 : 0.6,
                in_array($marca, ['HUAWEI', 'FORTINET'], true) ? $t['acento'] : $t['texto'], $esc($marca));
        }
        $o .= sprintf('<text x="%.1f" y="%.1f" font-family="Segoe UI, Arial, sans-serif" font-size="8" font-weight="600" fill="%s">%s</text>',
            $x, $cy + 5, $t['texto'], $esc(mb_strimwidth($s['serie'], 0, 18, '…')));
        // LEDs SYST / STAT
        $o .= sprintf('<circle cx="%.1f" cy="%.1f" r="1.5" fill="%s"/><circle cx="%.1f" cy="%.1f" r="1.5" fill="%s"/>',
            $x + 1.5, $cy + 14, $t['sub'], $x + 8, $cy + 14, $t['sub']);
        if ($s['poe']) {
            $o .= sprintf('<rect x="%.1f" y="%.1f" width="20" height="8" rx="2" fill="none" stroke="%s" stroke-width="0.8"/><text x="%.1f" y="%.1f" font-family="Segoe UI, Arial, sans-serif" font-size="5.5" font-weight="700" fill="%s" text-anchor="middle">PoE+</text>',
                $x + 16, $cy + 10, $t['acento'], $x + 26, $cy + 16, $t['acento']);
        }
        return $o;
    }

    private static function orejas(int $w, int $h): string
    {
        $o = '';
        foreach ([0, $w - 14] as $x) {
            $o .= sprintf('<rect x="%d" y="1" width="14" height="%d" rx="2" fill="url(#oreja)" stroke="#5b6470" stroke-width="0.6"/>', $x, $h - 2);
            foreach ($h > 70 ? [12, $h - 12] : [$h / 2] as $cy) {
                $o .= sprintf('<rect x="%d" y="%.1f" width="6" height="4.5" rx="2.2" fill="#3b4250"/>', $x + 4, $cy - 2.25);
            }
        }
        return $o;
    }

    /** Chasis modular: cabecera, ranuras (supervisoras y tarjetas de línea) y bahía de ventilación/fuentes. */
    private static function modular(array $s, array $t, int $w, int $h): string
    {
        $o = self::orejas($w, $h)
            . sprintf('<rect x="14" y="3" width="%d" height="%d" rx="4" fill="url(#cuerpo)" stroke="%s" stroke-width="1.6"/>', $w - 28, $h - 6, $t['contorno']);
        // Cabecera
        $o .= sprintf('<rect x="20" y="9" width="3" height="28" rx="1.5" fill="%s"/>', $t['acento']);
        $o .= self::marca($t, $s + ['poe' => false], 28, 30, 4);

        $y = 46;
        for ($i = 1; $i <= $s['ranuras']; $i++) {
            $sup = in_array($i, $s['sups'], true);
            $o .= sprintf('<rect x="22" y="%d" width="%d" height="26" rx="1.5" fill="%s" stroke="%s" stroke-width="0.8"/>', $y, $w - 44, $sup ? $t['c0'] : $t['c1'], $t['borde']);
            // Palancas de extracción
            $o .= sprintf('<rect x="24" y="%d" width="5" height="22" rx="1" fill="#9aa3ae"/><rect x="%d" y="%d" width="5" height="22" rx="1" fill="#9aa3ae"/>', $y + 2, $w - 29, $y + 2);
            $o .= sprintf('<text x="34" y="%d" font-family="Segoe UI, Arial, sans-serif" font-size="7" font-weight="700" fill="%s">%d</text>', $y + 16, $t['sub'], $i);
            if ($sup) {
                $o .= sprintf('<text x="48" y="%d" font-family="Segoe UI, Arial, sans-serif" font-size="7" font-weight="700" fill="%s">SUP</text>', $y + 16, $t['acento']);
                $o .= self::bloquesPuertosEn([['rj45', 2, 1], ['sfp', 8, 1]], $t, 78, $w - 36, $y, 26);
            } else {
                $o .= self::bloquesPuertosEn([['sfp', 24, 1]], $t, 48, $w - 36, $y, 26);
            }
            $y += 30;
        }
        // Ventilación y fuentes
        $o .= sprintf('<rect x="22" y="%d" width="%d" height="46" rx="2" fill="%s" stroke="%s" stroke-width="0.8"/>', $y + 2, $w - 44, $t['c2'], $t['borde']);
        $o .= sprintf('<rect x="28" y="%d" width="%d" height="20" rx="2" fill="url(#rejilla)"/>', $y + 6, $w - 56);
        $ancho = ($w - 56 - 18) / 4;
        for ($p = 0; $p < 4; $p++) {
            $px = 28 + $p * ($ancho + 6);
            $o .= sprintf('<rect x="%.1f" y="%d" width="%.1f" height="14" rx="1.5" fill="%s" stroke="%s" stroke-width="0.6"/><circle cx="%.1f" cy="%d" r="1.5" fill="%s"/>',
                $px, $y + 30, $ancho, $t['c1'], $t['borde'], $px + $ancho - 5, $y + 37, $t['sub']);
        }
        return $o;
    }

    /** Variante de bloquesPuertos para una franja horizontal (tarjetas de línea del chasis). */
    private static function bloquesPuertosEn(array $bloques, array $t, float $x0, float $x1, float $y, float $alto): string
    {
        return '<g transform="translate(0,' . $y . ')">' . self::bloquesPuertos($bloques, $t, $x0, $x1, (int) $alto) . '</g>';
    }

    /** Firewall de escritorio (FortiGate 40F–90F). */
    private static function escritorio(array $s, array $t, int $w, int $h): string
    {
        $o = sprintf('<rect x="4" y="6" width="%d" height="%d" rx="9" fill="url(#cuerpo)" stroke="%s" stroke-width="1.6"/>', $w - 8, $h - 18, $t['contorno'])
            . sprintf('<rect x="12" y="%d" width="24" height="4" rx="2" fill="#0b0b0c"/><rect x="%d" y="%d" width="24" height="4" rx="2" fill="#0b0b0c"/>', $h - 12, $w - 36, $h - 12)
            . sprintf('<rect x="5" y="7" width="%d" height="1.2" fill="#fff" opacity="0.15"/>', $w - 10)
            . sprintf('<rect x="12" y="14" width="3" height="%d" rx="1.5" fill="%s"/>', $h - 34, $t['acento']);
        $o .= self::marca($t, $s, 20, $h - 12);
        $o .= '<g transform="translate(0,-6)">' . self::bloquesPuertos([['rj45', $s['rj45'], 1], ['sfp', $s['sfp'], 1]], $t, 106, $w - 14, $h) . '</g>';
        return $o;
    }

    /** Access point de techo (vista superior). */
    private static function ap(array $s, int $w): string
    {
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_XML1);
        $c = $w / 2;
        return sprintf('<rect x="14" y="10" width="%d" height="%d" rx="46" fill="url(#blanco)" stroke="#c4cad2" stroke-width="2"/>', $w - 28, $w - 28)
            . sprintf('<rect x="30" y="26" width="%d" height="%d" rx="36" fill="none" stroke="#e5e8ec" stroke-width="2"/>', $w - 60, $w - 60)
            . sprintf('<circle cx="%d" cy="%d" r="15" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2"/><circle cx="%d" cy="%d" r="6" fill="#94a3b8"/>', $c, $c - 12, $c, $c - 12)
            . sprintf('<text x="%d" y="%d" font-family="Segoe UI, Arial, sans-serif" font-size="17" font-weight="600" fill="#6b7280" text-anchor="middle">%s</text>', $c, $c + 34, $esc($s['marca']))
            . sprintf('<text x="%d" y="%d" font-family="Segoe UI, Arial, sans-serif" font-size="12" fill="#9ca3af" text-anchor="middle">%s</text>', $c, $c + 52, $esc($s['serie']));
    }

    /** Unidad exterior de radioenlace (InfiNet). */
    private static function radio(array $s, int $w, int $h): string
    {
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_XML1);
        return sprintf('<rect x="%d" y="0" width="14" height="%d" rx="4" fill="#9aa3ae"/>', $w / 2 - 7, $h)
            . sprintf('<rect x="%d" y="%d" width="70" height="12" rx="3" fill="#6b7280"/>', $w / 2 - 35, $h / 2 - 6)
            . sprintf('<rect x="20" y="20" width="%d" height="%d" rx="20" fill="url(#blanco)" stroke="#b8c0ca" stroke-width="2"/>', $w - 40, $h - 60)
            . sprintf('<rect x="36" y="36" width="%d" height="%d" rx="12" fill="none" stroke="#e2e6eb" stroke-width="2"/>', $w - 72, $h - 92)
            . sprintf('<text x="%d" y="%d" font-family="Segoe UI, Arial, sans-serif" font-size="18" font-weight="700" fill="#1f6fb2" text-anchor="middle">%s</text>', $w / 2, $h / 2 - 8, $esc($s['marca']))
            . sprintf('<text x="%d" y="%d" font-family="Segoe UI, Arial, sans-serif" font-size="12" fill="#6b7280" text-anchor="middle">%s</text>', $w / 2, $h / 2 + 12, $esc(str_replace('InfiNet ', '', $s['serie'])))
            . sprintf('<circle cx="%d" cy="%d" r="4" fill="#94a3b8"/>', $w / 2, $h - 52);
    }

    private static function slug(string $v): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($v)), '-');
    }
}
