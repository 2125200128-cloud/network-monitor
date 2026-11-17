<?php

namespace App\Services\Recaudadoras;

use Illuminate\Support\Str;

/**
 * Municipio de una recaudadora a partir de su nombre (casi todas se llaman como su municipio). Usa los 125 municipios
 * de public/data/jalisco-municipios.json (INEGI), el mismo archivo que dibuja el mapa.
 */
class MunicipiosJalisco
{
    /** Localidades, delegaciones y nombres abreviados → municipio (INEGI). Claves ya normalizadas. */
    private const ALIAS = [
        'ciudad guzman' => 'Zapotlán el Grande',
        'ciudad guzman coord' => 'Zapotlán el Grande',
        'auditoria fiscal ciudad guzman' => 'Zapotlán el Grande',
        'el tuito' => 'Cabo Corrientes',
        'san luis soyatlan' => 'Tuxcueca',
        'san jose de gracia' => 'Tepatitlán de Morelos',
        'capilla de guadalupe' => 'Tepatitlán de Morelos',
        'cajititlan' => 'Tlajomulco de Zúñiga',
        'santa anita' => 'San Pedro Tlaquepaque',
        'tlaquepaque' => 'San Pedro Tlaquepaque',
        'las aguilas' => 'Zapopan',
        'cis zapopan' => 'Zapopan',
        'arenal' => 'El Arenal',
        'san sebastian del o' => 'San Sebastián del Oeste',
        'san juanito escobedo' => 'San Juanito de Escobedo',
        'union tula' => 'Unión de Tula',
        'ojuelos' => 'Ojuelos de Jalisco',
        'ahualulco del mercado' => 'Ahualulco de Mercado',
        'tlajomulco' => 'Tlajomulco de Zúñiga',
    ];

    private static ?array $lista = null;

    /** @return array<int, array{cve: string, nombre: string, norm: string, lat: float, lon: float}> */
    public static function todos(): array
    {
        if (self::$lista === null) {
            $datos = json_decode((string) @file_get_contents(public_path('data/jalisco-municipios.json')), true) ?: [];
            self::$lista = array_map(fn ($m) => [
                'cve' => $m['cve'], 'nombre' => $m['nombre'], 'norm' => self::normalizar($m['nombre']),
                'lat' => (float) $m['cabecera']['lat'], 'lon' => (float) $m['cabecera']['lon'],
            ], $datos['municipios'] ?? []);
        }
        return self::$lista;
    }

    public static function porClave(?string $cve): ?array
    {
        foreach (self::todos() as $m) {
            if ($m['cve'] === $cve) {
                return $m;
            }
        }
        return null;
    }

    /** Municipio por el nombre de la recaudadora; las metropolitanas sin municipio en el nombre quedan en Guadalajara. */
    public static function resolver(string $nombre, ?string $coordinadora = null): ?array
    {
        $n = self::normalizar($nombre);
        $todos = self::todos();
        $buscar = fn (string $norm) => collect($todos)->firstWhere('norm', $norm);

        if ($m = $buscar($n)) {
            return $m;
        }
        if (isset(self::ALIAS[$n]) && ($m = $buscar(self::normalizar(self::ALIAS[$n])))) {
            return $m;
        }
        // «Tepatitlán» → «Tepatitlán de Morelos»: el nombre de la recaudadora es el inicio del municipio
        $porInicio = array_values(array_filter($todos, fn ($m) => str_starts_with($m['norm'], $n . ' ')));
        if (count($porInicio) === 1) {
            return $porInicio[0];
        }
        // «Recaudadora Zapopan Centro» → Zapopan: el municipio va dentro del nombre (el más largo, para no confundir)
        $dentro = array_filter($todos, fn ($m) => preg_match('/\b' . preg_quote($m['norm'], '/') . '\b/', $n));
        if ($dentro) {
            usort($dentro, fn ($a, $b) => strlen($b['norm']) <=> strlen($a['norm']));
            return $dentro[0];
        }
        if ($coordinadora !== null && self::normalizar($coordinadora) === 'metropolitano') {
            return $buscar('guadalajara');
        }
        return null;
    }

    public static function normalizar(string $texto): string
    {
        $t = Str::lower(Str::ascii($texto));
        $t = preg_replace(['/\bsta\b\.?/', '/\bsto\b\.?/', '/\bma\b\.?/', '/\bcd\b\.?/', '/\bsn\b\.?/', '/\bgral\b\.?/'],
            ['santa', 'santo', 'maria', 'ciudad', 'san', 'general'], $t);
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t);
        return trim(preg_replace('/\s+/', ' ', $t));
    }
}
