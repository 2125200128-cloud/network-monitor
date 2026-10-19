<?php

namespace App\Console\Commands;

use App\Models\SitioRed;
use Illuminate\Console\Command;

/**
 * Importa (o actualiza) los sitios de un extracto de OpenStreetMap. Es idempotente: identifica cada sitio
 * por su referencia OSM, así que repetirlo no duplica ni toca las asignaciones de equipos.
 */
class ImportarSitiosCommand extends Command
{
    protected $signature = 'noc:importar-sitios {archivo=database/data/sitios-zmg-osm.json : Extracto generado desde OpenStreetMap}';

    protected $description = 'Importa sitios (dependencias, recaudadoras, escuelas…) desde un extracto de OpenStreetMap';

    public function handle(): int
    {
        $ruta = base_path($this->argument('archivo'));
        if (!is_file($ruta)) {
            $this->error("No existe el archivo {$ruta}");
            return self::FAILURE;
        }
        $datos = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);

        $filas = collect($datos['sitios'])
            ->filter(fn ($s) => in_array($s['categoria'], SitioRed::CATEGORIAS, true))
            ->map(fn ($s) => [
                'origen' => 'osm',
                'origen_ref' => $s['ref'],
                'nombre' => $s['nombre'],
                'categoria' => $s['categoria'],
                'direccion' => $s['direccion'],
                'municipio' => $s['municipio'] ?? null,
                'operador' => $s['operador'],
                'lat' => $s['lat'],
                'lon' => $s['lon'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $antes = SitioRed::where('origen', 'osm')->count();
        foreach ($filas->chunk(500) as $bloque) {
            SitioRed::upsert($bloque->values()->all(), ['origen_ref'],
                ['nombre', 'categoria', 'direccion', 'municipio', 'operador', 'lat', 'lon', 'updated_at']);
        }
        $despues = SitioRed::where('origen', 'osm')->count();

        // Lo que salió del extracto se retira, salvo que ya tenga equipos asignados (eso lo decide el NOC)
        $retirados = SitioRed::where('origen', 'osm')
            ->whereNotIn('origen_ref', $filas->pluck('origen_ref'))
            ->whereDoesntHave('dispositivos')
            ->delete();

        $this->info(sprintf('%d sitios en el archivo · %d nuevos · %d actualizados · %d retirados. Fuente: %s (%s)',
            $filas->count(), $despues - $antes, $filas->count() - ($despues - $antes), $retirados, $datos['fuente'] ?? 'OSM', $datos['extraido'] ?? 's/f'));
        return self::SUCCESS;
    }
}
