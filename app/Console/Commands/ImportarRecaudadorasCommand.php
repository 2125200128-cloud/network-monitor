<?php

namespace App\Console\Commands;

use App\Services\Recaudadoras\ImportadorRecaudadoras;
use Illuminate\Console\Command;

/** Importa (o actualiza) las recaudadoras desde el Excel de Enlaces de Telecomunicaciones. Se puede repetir. */
class ImportarRecaudadorasCommand extends Command
{
    protected $signature = 'recaudadoras:importar {archivo : Ruta del .xlsx}';

    protected $description = 'Importa las recaudadoras, sus enlaces y antenas desde el Excel de Enlaces de Telecomunicaciones';

    public function handle(ImportadorRecaudadoras $importador): int
    {
        $ruta = $this->argument('archivo');
        if (!is_file($ruta)) {
            $this->error("No existe el archivo {$ruta}");
            return self::FAILURE;
        }
        try {
            $r = $importador->importar($ruta);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Recaudadoras nuevas: {$r['creadas']} · completadas con datos que les faltaban: {$r['actualizadas']} · oficinas LADA nuevas: {$r['oficinas']} · equipos nuevos: {$r['equipos']}");
        foreach ($r['avisos'] as $aviso) {
            $this->warn("  · {$aviso}");
        }
        if ($r['sin_municipio']) {
            $this->warn('Sin municipio (no aparecen en el mapa hasta asignarlo en su ficha): ' . implode(', ', $r['sin_municipio']));
        }
        return self::SUCCESS;
    }
}
