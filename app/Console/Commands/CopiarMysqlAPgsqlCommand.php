<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copia todos los datos de la base MySQL original (conexión "mysql_origen") a PostgreSQL.
 *
 * Requisitos: la base PostgreSQL ya creada y migrada (`php artisan migrate --database=pgsql`).
 * - Conserva los ids y vuelve a ajustar las secuencias.
 * - Convierte los tipos que MySQL guarda distinto: booleanos (0/1), fechas "cero" (0000-00-00) y JSON vacío.
 * - Desactiva temporalmente las llaves foráneas en PostgreSQL para copiar en cualquier orden.
 * - Al final compara el número de filas de cada tabla.
 * No modifica la base MySQL.
 */
class CopiarMysqlAPgsqlCommand extends Command
{
    protected $signature = 'db:copiar-a-pgsql
        {--origen=mysql_origen : Conexión MySQL de origen}
        {--destino=pgsql : Conexión PostgreSQL de destino}
        {--bloque=1000 : Filas por inserción}
        {--forzar : Vacía las tablas de destino que ya tengan datos}';

    protected $description = 'Copia los datos de MySQL a PostgreSQL (conserva ids, convierte tipos y verifica conteos).';

    /** Tablas que no se copian: las crea y llena la propia migración. */
    private const EXCLUIR = ['migrations'];

    public function handle(): int
    {
        $origen = DB::connection($this->option('origen'));
        $destino = DB::connection($this->option('destino'));
        if ($destino->getDriverName() !== 'pgsql') {
            $this->error('La conexión de destino no es PostgreSQL.');
            return self::FAILURE;
        }

        $tablas = collect($origen->select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = ?', ['BASE TABLE']))
            ->pluck('t')->reject(fn ($t) => in_array($t, self::EXCLUIR, true))->values();
        $faltantes = $tablas->reject(fn ($t) => Schema::connection($this->option('destino'))->hasTable($t));
        if ($faltantes->isNotEmpty()) {
            $this->error('Faltan tablas en PostgreSQL (¿ejecutaste las migraciones?): ' . $faltantes->implode(', '));
            return self::FAILURE;
        }

        $conDatos = $tablas->filter(fn ($t) => $destino->table($t)->exists());
        if ($conDatos->isNotEmpty() && !$this->option('forzar')) {
            $this->error('Estas tablas de PostgreSQL ya tienen datos: ' . $conDatos->implode(', ') . '. Usa --forzar para vaciarlas.');
            return self::FAILURE;
        }

        // Padres antes que hijos, para que las llaves foráneas se cumplan al insertar
        $tablas = $this->ordenarPorDependencias($destino, $tablas->all());

        // Con superusuario además se desactivan llaves foráneas y triggers durante la copia
        $sinLlaves = false;
        try {
            $destino->statement('SET session_replication_role = replica');
            $sinLlaves = true;
        } catch (\Throwable) {
            $this->line('El usuario de PostgreSQL no es superusuario: se copia en orden de dependencias con llaves activas.');
        }
        try {
            if ($conDatos->isNotEmpty()) {
                $destino->statement('TRUNCATE ' . $conDatos->map(fn ($t) => '"' . $t . '"')->implode(', ') . ' RESTART IDENTITY CASCADE');
            }

            $resultado = [];
            foreach ($tablas as $tabla) {
                $copiadas = $this->copiarTabla($origen, $destino, $tabla);
                $resultado[] = [$tabla, $origen->table($tabla)->count(), $copiadas, $destino->table($tabla)->count()];
            }
        } finally {
            if ($sinLlaves) {
                $destino->statement('SET session_replication_role = DEFAULT');
            }
        }

        $this->ajustarSecuencias($destino, $tablas->all());

        $this->table(['Tabla', 'Filas MySQL', 'Copiadas', 'Filas PostgreSQL'], $resultado);
        if ($this->fueraDeRango) {
            $this->warn("{$this->fueraDeRango} valores mayores que BIGINT (contadores rotos) se guardaron como NULL.");
        }
        $diferentes = collect($resultado)->filter(fn ($r) => $r[1] !== $r[3]);
        if ($diferentes->isNotEmpty()) {
            $this->warn('Conteos distintos (pudo cambiar el origen mientras se copiaba): ' . $diferentes->pluck(0)->implode(', '));
            return self::FAILURE;
        }
        $this->info('Copia completa: ' . count($resultado) . ' tablas con conteos idénticos.');
        return self::SUCCESS;
    }

    private function copiarTabla($origen, $destino, string $tabla): int
    {
        // Tipos de las columnas en PostgreSQL para convertir los valores de MySQL
        $tipos = collect(Schema::connection($this->option('destino'))->getColumns($tabla))
            ->mapWithKeys(fn ($c) => [$c['name'] => strtolower($c['type_name'])]);
        $columnasOrigen = collect(Schema::connection($this->option('origen'))->getColumns($tabla))->pluck('name');
        $columnas = $columnasOrigen->filter(fn ($c) => $tipos->has($c))->values();
        $sobrantes = $columnasOrigen->diff($columnas);
        if ($sobrantes->isNotEmpty()) {
            $this->warn("{$tabla}: columnas que no existen en PostgreSQL y se omiten: " . $sobrantes->implode(', '));
        }

        $consulta = $origen->table($tabla)->select($columnas->all());
        $orden = $columnas->contains('id') ? 'id' : $columnas->first();
        $total = 0;
        $consulta->orderBy($orden)->chunk((int) $this->option('bloque'), function ($filas) use ($destino, $tabla, $tipos, &$total) {
            $lote = $filas->map(function ($fila) use ($tipos) {
                $fila = (array) $fila;
                foreach ($fila as $col => $valor) {
                    $fila[$col] = $this->convertir($valor, $tipos[$col]);
                }
                return $fila;
            })->all();
            $destino->table($tabla)->insert($lote);
            $total += count($lote);
        });
        $this->line(sprintf('  %-28s %8d filas', $tabla, $total));
        return $total;
    }

    private function convertir($valor, string $tipo)
    {
        if ($valor === null) {
            return null;
        }
        if ($tipo === 'bool' || $tipo === 'boolean') {
            return (bool) $valor;
        }
        if (str_starts_with($tipo, 'timestamp') || $tipo === 'date') {
            return str_starts_with((string) $valor, '0000-00-00') ? null : $valor;
        }
        if ($tipo === 'json' || $tipo === 'jsonb') {
            // MySQL acepta '' en columnas JSON; PostgreSQL no
            return trim((string) $valor) === '' ? null : $valor;
        }
        if ($tipo === 'int8' && $this->fueraDeBigint($valor)) {
            // BIGINT UNSIGNED de MySQL por encima de 2^63-1: contador roto del equipo, no un dato real
            $this->fueraDeRango++;
            return null;
        }
        return $valor;
    }

    private int $fueraDeRango = 0;

    private function fueraDeBigint($valor): bool
    {
        if (is_int($valor)) {
            return false;
        }
        $texto = (string) $valor;
        if (!ctype_digit($texto)) {
            return is_float($valor) && $valor > PHP_INT_MAX;
        }
        $texto = ltrim($texto, '0');
        return strlen($texto) > 19 || (strlen($texto) === 19 && strcmp($texto, (string) PHP_INT_MAX) > 0);
    }

    /** Orden topológico por llaves foráneas de PostgreSQL (las tablas referenciadas primero). */
    private function ordenarPorDependencias($destino, array $tablas): \Illuminate\Support\Collection
    {
        $dependeDe = array_fill_keys($tablas, []);
        $llaves = $destino->select("
            SELECT tc.table_name AS hija, ccu.table_name AS padre
            FROM information_schema.table_constraints tc
            JOIN information_schema.constraint_column_usage ccu
              ON ccu.constraint_name = tc.constraint_name AND ccu.constraint_schema = tc.constraint_schema
            WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = current_schema()
        ");
        foreach ($llaves as $l) {
            if (isset($dependeDe[$l->hija]) && $l->hija !== $l->padre && isset($dependeDe[$l->padre])) {
                $dependeDe[$l->hija][$l->padre] = true;
            }
        }

        $orden = [];
        while ($dependeDe) {
            $listas = array_keys(array_filter($dependeDe, fn ($padres) => !array_diff_key($padres, array_flip($orden))));
            if (!$listas) {
                $listas = array_keys($dependeDe); // ciclo: se agregan las restantes tal cual
            }
            foreach ($listas as $t) {
                $orden[] = $t;
                unset($dependeDe[$t]);
            }
        }
        return collect($orden);
    }

    /** Cada secuencia de "id" continúa después del mayor id copiado. */
    private function ajustarSecuencias($destino, array $tablas): void
    {
        foreach ($tablas as $tabla) {
            if (!Schema::connection($this->option('destino'))->hasColumn($tabla, 'id')) {
                continue;
            }
            $secuencia =$destino->selectOne('SELECT pg_get_serial_sequence(?, ?) AS s', [$tabla, 'id'])?->s;
            if ($secuencia) {
                $destino->statement("SELECT setval(?, COALESCE((SELECT MAX(id) FROM \"{$tabla}\"), 1), (SELECT MAX(id) IS NOT NULL FROM \"{$tabla}\"))", [$secuencia]);
            }
        }
    }
}
