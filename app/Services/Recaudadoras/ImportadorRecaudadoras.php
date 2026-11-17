<?php

namespace App\Services\Recaudadoras;

use App\Models\Recaudadora;
use App\Models\RecaudadoraEquipo;
use App\Support\LectorXlsx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa el Excel «Enlaces Telecomunicaciones»:
 *   - Hoja «Recaudadoras»: una fila por recaudadora con sus enlaces, IP administrativa y loopback.
 *   - Otra hoja con «LADA ENLACES» (oficinas de Hacienda con servicio Telmex, sin IP) y una sección «Antenas»
 *     (número de recaudadora, nombre, IP): las antenas quedan como equipos de su recaudadora.
 * El Excel solo sirve para la carga inicial: la base de datos es la fuente de verdad y todo se corrige desde el sistema.
 * Se puede repetir sin riesgo: identifica cada recaudadora por su número (oficinas por su nombre), agrega las que falten,
 * llena los campos que estén vacíos y, si el Excel trae otro valor, lo avisa y deja el del sistema. No borra nada.
 */
class ImportadorRecaudadoras
{
    /** Encabezado normalizado → columna de la tabla. «REFERENCIA» después de «BESTEL» es la de Bestel. */
    private const COLUMNAS = [
        'numero' => 'numero', 'coordinadora' => 'coordinadora', 'recaudadora' => 'nombre',
        'referencia' => 'referencia', 'tipo de enlace' => 'tipo_enlace', 'uninet' => 'uninet',
        'id sitio red jalisco' => 'id_sitio_red_jalisco', 'velocidad red jalisco' => 'velocidad_red_jalisco',
        'bestel' => 'bestel', 'global king' => 'global_king', 'enlace backup' => 'enlace_backup',
        'gobierno del estado de jalisco' => 'gobierno_estado', 'hemac' => 'hemac',
        'ip administrativa' => 'ip_administrativa', 'ip loopback' => 'ip_loopback', 'numero de referencia' => 'numero_referencia',
    ];

    private const LARGOS = [
        'numero' => 20, 'coordinadora' => 30, 'nombre' => 150, 'referencia' => 80, 'tipo_enlace' => 40, 'uninet' => 120,
        'id_sitio_red_jalisco' => 60, 'velocidad_red_jalisco' => 60, 'bestel' => 255, 'bestel_referencia' => 80,
        'global_king' => 60, 'enlace_backup' => 80, 'gobierno_estado' => 60, 'hemac' => 60, 'numero_referencia' => 120,
    ];

    private array $avisos = [];
    private array $contador = ['creadas' => 0, 'actualizadas' => 0, 'oficinas' => 0, 'equipos' => 0];
    /** clave → datos ya leídos en esta importación (para combinar filas repetidas) */
    private array $vistas = [];

    /** @return array{creadas: int, actualizadas: int, oficinas: int, equipos: int, avisos: string[], sin_municipio: string[]} */
    public function importar(string $ruta): array
    {
        $hojas = LectorXlsx::hojas($ruta);
        [$nombreHoja, $filas, $encabezado] = $this->hojaPrincipal($hojas);

        $registros = $this->leerRecaudadoras($filas, $encabezado);
        $extras = [];
        foreach ($hojas as $nombre => $otras) {
            if ($nombre !== $nombreHoja) {
                $extras = array_merge($extras, $this->leerHojaExtra($otras));
            }
        }

        DB::transaction(function () use ($registros, $extras) {
            foreach ($registros as $r) {
                $this->guardar($r['datos'], $r['equipos']);
            }
            foreach ($extras['oficinas'] ?? [] as $of) {
                $this->guardarOficinaLada($of);
            }
            foreach ($extras['antenas'] ?? [] as $antena) {
                $this->guardarAntena($antena);
            }
        });

        $sinMunicipio = Recaudadora::whereNull('municipio_cve')->where('tipo', 'recaudadora')->get()->map->titulo()->all();
        return $this->contador + ['avisos' => $this->avisos, 'sin_municipio' => $sinMunicipio];
    }

    /** La hoja cuyo encabezado trae «RECAUDADORA» (y la fila donde está). */
    private function hojaPrincipal(array $hojas): array
    {
        foreach ($hojas as $nombre => $filas) {
            foreach (array_slice($filas, 0, 10, true) as $i => $fila) {
                $norm = array_map([MunicipiosJalisco::class, 'normalizar'], $fila);
                if (in_array('recaudadora', $norm, true) && in_array('ip administrativa', $norm, true)) {
                    return [$nombre, array_slice($filas, $i + 1), $norm];
                }
            }
        }
        throw new \RuntimeException('No encontré una hoja con las columnas «RECAUDADORA» e «IP ADMINISTRATIVA».');
    }

    private function leerRecaudadoras(array $filas, array $encabezado): array
    {
        $mapa = [];
        $trasBestel = false;
        foreach ($encabezado as $i => $titulo) {
            if ($titulo === 'bestel') {
                $trasBestel = true;
            }
            if ($titulo === 'referencia' && $trasBestel) {
                $mapa[$i] = 'bestel_referencia';
                continue;
            }
            if (isset(self::COLUMNAS[$titulo]) && !in_array(self::COLUMNAS[$titulo], $mapa, true)) {
                $mapa[$i] = self::COLUMNAS[$titulo];
            }
        }

        $registros = [];
        foreach ($filas as $fila) {
            $d = [];
            foreach ($mapa as $i => $campo) {
                $d[$campo] = trim((string) ($fila[$i] ?? ''));
            }
            if (($d['nombre'] ?? '') === '') {
                // Fila de continuación (celda combinada): sus IPs van como nota de la anterior
                $ips = array_filter([$d['ip_administrativa'] ?? '', $d['ip_loopback'] ?? '']);
                if ($ips && $registros) {
                    $ultimo = &$registros[array_key_last($registros)];
                    $ultimo['datos']['notas'] = trim(($ultimo['datos']['notas'] ?? '') . "\nFila adicional en el Excel: IP administrativa "
                        . ($d['ip_administrativa'] ?: '—') . ', loopback ' . ($d['ip_loopback'] ?: '—'));
                    unset($ultimo);
                }
                continue;
            }
            $registros[] = $this->limpiar($d);
        }
        return $registros;
    }

    /** Normaliza una fila: tipo, clave, IPs (la primera es la principal; otras IPs administrativas → equipos). */
    private function limpiar(array $d): array
    {
        foreach ($d as $k => $v) {
            $d[$k] = preg_replace('/\s*\n\s*/', ' · ', $v);
        }
        $numero = strtoupper($d['numero'] ?? '');
        $esReca = (bool) preg_match('/^\d+[A-Z]?$/', $numero);
        $d['tipo'] = $esReca ? 'recaudadora' : 'oficina';
        $d['numero'] = $numero !== '' ? $numero : null;
        $d['clave'] = $esReca
            ? 'R-' . (preg_replace('/^0+(?=\d)/', '', $numero))
            : 'OF-' . Str::limit(Str::slug($numero !== '' ? "{$numero} {$d['nombre']}" : $d['nombre']), 36, '');

        $titulo = ($d['numero'] ? "{$d['numero']} " : '') . $d['nombre'];
        $equipos = [];

        // IP administrativa: puede traer máscara (/27) o varias IPs con su descripción, una por línea
        preg_match_all('/(?<![\d.])(\d{1,3}(?:\.\d{1,3}){3})(?![\d.])(?:\/\d{1,2})?\s*([^\d·]*)/u', $d['ip_administrativa'] ?? '', $m, PREG_SET_ORDER);
        $validas = array_values(array_filter($m, fn ($x) => filter_var($x[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)));
        $original = $d['ip_administrativa'] ?? '';
        $d['ip_administrativa'] = $validas[0][1] ?? null;
        foreach (array_slice($validas, 1) as $extra) {
            $equipos[] = ['tipo' => 'otro', 'nombre' => trim($extra[2]) ?: 'IP administrativa adicional', 'ip' => $extra[1]];
        }
        if (!$validas && $original !== '') {
            $this->avisos[] = "{$titulo}: la IP administrativa «{$original}» no es una IP; queda sin IP.";
        } elseif (count($validas) > 1) {
            $this->avisos[] = "{$titulo}: trae " . count($validas) . ' IPs administrativas; la principal es ' . $d['ip_administrativa'] . ' y las demás quedan como equipos.';
        }

        $lb = $d['ip_loopback'] ?? '';
        preg_match('/(?<![\d.])(\d{1,3}(?:\.\d{1,3}){3})(?![\d.])/', $lb, $ml);
        $d['ip_loopback'] = isset($ml[1]) && filter_var($ml[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ml[1] : null;
        if (!$d['ip_loopback'] && $lb !== '' && $lb !== '*') {
            $this->avisos[] = "{$titulo}: la loopback «{$lb}» no es una IP válida; queda vacía.";
        }

        // Coordinadora: un número o «Metropolitano»; otra cosa (p. ej. una «t» suelta) es errata y no se toma
        $coord = trim((string) ($d['coordinadora'] ?? ''));
        if (preg_match('/^0*(\d{1,2})$/', $coord, $mc)) {
            $d['coordinadora'] = $mc[1];
        } elseif (str_starts_with(mb_strtolower($coord), 'metropolitan')) {
            $d['coordinadora'] = 'Metropolitano';
        } else {
            if ($coord !== '') {
                $this->avisos[] = "{$titulo}: la coordinadora «{$coord}» no es un número; se deja la que tenía (se corrige en su ficha).";
            }
            $d['coordinadora'] = null;
        }

        foreach (self::LARGOS as $campo => $largo) {
            if (isset($d[$campo])) {
                $d[$campo] = $d[$campo] === '' ? null : Str::limit($d[$campo], $largo - 1, '…');
            }
        }
        return ['datos' => $d, 'equipos' => $equipos];
    }

    private function guardar(array $d, array $equipos): void
    {
        // Filas repetidas (p. ej. dos veces la 19 con la misma IP): se combinan; con otra IP, la segunda lleva sufijo
        if (isset($this->vistas[$d['clave']])) {
            $previa = $this->vistas[$d['clave']];
            if ($previa['ip_administrativa'] === $d['ip_administrativa']) {
                $this->avisos[] = "{$d['nombre']}: repite el número {$d['numero']} con la misma IP que «{$previa['nombre']}»; se combinaron.";
                $d = array_merge($d, array_filter($previa, fn ($v) => $v !== null && $v !== ''));
            } else {
                $d['clave'] .= '-2';
                $this->avisos[] = "{$d['nombre']}: repite el número {$d['numero']} con otra IP; se guardó como {$d['clave']}.";
            }
        }
        $this->vistas[$d['clave']] = $d;

        $reca = Recaudadora::firstOrNew(['clave' => $d['clave']]);
        $nueva = !$reca->exists;
        $notasExcel = $d['notas'] ?? null;
        unset($d['notas']);

        if ($nueva) {
            $reca->fill(array_filter($d, fn ($v) => $v !== null));
        } else {
            // La base de datos manda: del Excel solo se llenan los campos vacíos; si trae otro valor, se avisa y se deja el del sistema
            $distintos = [];
            $llenados = 0;
            foreach ($d as $campo => $valor) {
                if ($campo === 'clave' || $valor === null || $valor === '') {
                    continue;
                }
                $actual = $reca->getAttribute($campo);
                if ($actual === null || $actual === '') {
                    $reca->setAttribute($campo, $valor);
                    $llenados++;
                } elseif (mb_strtolower(trim((string) $actual)) !== mb_strtolower(trim((string) $valor))) {
                    $distintos[] = Recaudadora::etiquetaCampo($campo) . ": Excel «{$valor}», sistema «{$actual}»";
                }
            }
            if ($distintos) {
                $this->avisos[] = "{$reca->titulo()}: el Excel trae datos distintos a los del sistema; se dejaron los del sistema ("
                    . implode('; ', $distintos) . ').';
            }
            if ($llenados) {
                $this->contador['actualizadas']++;
            }
        }
        if ($nueva || !$reca->municipio_cve) {
            $mun = MunicipiosJalisco::resolver($reca->nombre, $reca->coordinadora);
            $reca->municipio_cve = $mun['cve'] ?? null;
            $reca->municipio = $mun['nombre'] ?? null;
        }
        if ($notasExcel && !str_contains((string) $reca->notas, $notasExcel)) {
            $reca->notas = trim(($reca->notas ? $reca->notas . "\n" : '') . $notasExcel);
        }
        $reca->save();
        if ($nueva) {
            $this->contador['creadas']++;
        }

        foreach ($equipos as $e) {
            $this->guardarEquipo($reca, $e);
        }
    }

    /** Hoja secundaria: oficinas con servicio LADA (sin IP) y la sección «Antenas». */
    private function leerHojaExtra(array $filas): array
    {
        $oficinas = [];
        $antenas = [];
        $enAntenas = false;
        foreach ($filas as $fila) {
            $celdas = array_values(array_filter(array_map('trim', $fila), fn ($v) => $v !== ''));
            $norm = array_map([MunicipiosJalisco::class, 'normalizar'], $fila);
            if (in_array('antenas', $norm, true)) {
                $enAntenas = true;
                continue;
            }
            if ($enAntenas) {
                $ip = collect($celdas)->first(fn ($v) => filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
                $numero = preg_match('/^\d+[A-Z]?$/i', trim((string) ($fila[0] ?? ''))) ? trim($fila[0]) : null;
                $nombre = collect($celdas)->first(fn ($v) => $v !== $numero && $v !== $ip);
                if ($ip && $nombre) {
                    $antenas[] = ['numero' => $numero, 'nombre' => $nombre, 'ip' => $ip];
                } elseif ($nombre) {
                    $resto = collect($celdas)->reject(fn ($v) => $v === $nombre || $v === $numero)->implode(' ');
                    $this->avisos[] = "Antena «{$nombre}»: sin IP en el Excel" . ($resto !== '' ? " (dice «{$resto}»)" : '') . '; no se agregó.';
                }
                continue;
            }
            // LADA: Categoría | Dependencia | Representación | Referencia | Servicios
            $i = array_search(true, array_map(fn ($c) => str_starts_with($c, 'lada') || str_contains($c, 'fast ethernet'), $norm), true);
            if ($i !== false && isset($fila[$i + 2])) {
                // Tal como viene (en mayúsculas: SAT, DTI…), sin el prefijo FINANZAS ni espacios dobles
                $nombre = trim(preg_replace(['/^FINANZAS\s+/i', '/\s+/'], ['', ' '], $fila[$i + 2]));
                $clave = MunicipiosJalisco::normalizar($nombre);
                $oficinas[$clave] ??= ['nombre' => $nombre, 'categoria' => trim($fila[$i]), 'referencias' => []];
                if (trim($fila[$i + 3] ?? '') !== '') {
                    $oficinas[$clave]['referencias'][] = trim($fila[$i + 3]);
                }
            }
        }
        return ['oficinas' => array_values($oficinas), 'antenas' => $antenas];
    }

    private function guardarOficinaLada(array $of): void
    {
        $refs = implode(', ', array_unique($of['referencias']));
        // Si ya existe con ese nombre (p. ej. Colegio de Notarios viene en las dos hojas), solo se agregan sus referencias
        $norm = MunicipiosJalisco::normalizar($of['nombre']);
        $existente = Recaudadora::all()->first(fn ($r) => MunicipiosJalisco::normalizar($r->nombre) === $norm);
        if ($existente) {
            if ($refs && !str_contains((string) $existente->notas, $refs)) {
                $existente->notas = trim(($existente->notas ? $existente->notas . "\n" : '') . "Servicio {$of['categoria']}: {$refs}");
                $existente->save();
            }
            return;
        }
        // Solo se crea si no existe: una oficina que ya está en el sistema (aunque se le haya cambiado el nombre) no se toca
        $reca = Recaudadora::firstOrNew(['clave' => 'OF-' . Str::limit(Str::slug($of['nombre']), 36, '')]);
        if ($reca->exists) {
            return;
        }
        $reca->fill(['tipo' => 'oficina', 'nombre' => $of['nombre'], 'tipo_enlace' => Str::limit($of['categoria'], 39, ''),
            'referencia' => Str::limit($refs, 79, '…')]);
        $reca->save();
        $this->contador['oficinas']++;
    }

    private function guardarAntena(array $a): void
    {
        $reca = null;
        if ($a['numero'] !== null) {
            $reca = Recaudadora::where('clave', 'R-' . preg_replace('/^0+(?=\d)/', '', strtoupper($a['numero'])))->first();
        }
        if (!$reca) {
            // Sin número: por nombre («Zapopan 1» → la recaudadora que se llama Zapopan)
            $base = MunicipiosJalisco::normalizar(preg_replace('/\s+\d+$/', '', $a['nombre']));
            $reca = Recaudadora::where('tipo', 'recaudadora')->get()->first(fn ($r) => MunicipiosJalisco::normalizar($r->nombre) === $base);
        }
        if (!$reca) {
            $this->avisos[] = "Antena «{$a['nombre']}» ({$a['ip']}): no encontré a qué recaudadora pertenece; no se agregó.";
            return;
        }
        $this->guardarEquipo($reca, ['tipo' => 'antena', 'nombre' => "Antena {$a['nombre']}", 'ip' => $a['ip']]);
    }

    private function guardarEquipo(Recaudadora $reca, array $e): void
    {
        $equipo = RecaudadoraEquipo::firstOrNew(['recaudadora_id' => $reca->id, 'ip' => $e['ip']]);
        if (!$equipo->exists) {
            $equipo->fill(['tipo' => $e['tipo'], 'nombre' => Str::limit($e['nombre'], 119, '')])->save();
            $this->contador['equipos']++;
        }
    }
}
