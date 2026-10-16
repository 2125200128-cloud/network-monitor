<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Fragmentos de SQL que cambian entre MySQL y PostgreSQL. El resto de las consultas crudas del
 * proyecto se escriben en SQL estándar (CASE, CAST, COALESCE) para funcionar en ambos motores.
 */
class Sql
{
    public static function esPgsql(?string $conexion = null): bool
    {
        return DB::connection($conexion)->getDriverName() === 'pgsql';
    }

    /** ROUND(expr, n) también para valores de punto flotante (PostgreSQL solo redondea numeric). */
    public static function redondear(string $expr, int $decimales): string
    {
        return "ROUND(CAST({$expr} AS DECIMAL(20,6)), {$decimales})";
    }

    /** Cuenta filas que cumplen la condición (SUM(condición) no existe en PostgreSQL). */
    public static function contarSi(string $condicion): string
    {
        return "SUM(CASE WHEN {$condicion} THEN 1 ELSE 0 END)";
    }

    /** Segundos entre dos marcas de tiempo (b - a). */
    public static function segundosEntre(string $a, string $b): string
    {
        return self::esPgsql() ? "EXTRACT(EPOCH FROM ({$b} - {$a}))" : "TIMESTAMPDIFF(SECOND, {$a}, {$b})";
    }

    /** Segundos desde 1970 de una columna de fecha. */
    public static function epoch(string $col): string
    {
        return self::esPgsql() ? "EXTRACT(EPOCH FROM {$col})" : "UNIX_TIMESTAMP({$col})";
    }

    /** Inicio de la hora de una fecha, como fecha (para guardar en columnas DATETIME/TIMESTAMP). */
    public static function inicioDeHora(string $col): string
    {
        return self::esPgsql() ? "date_trunc('hour', {$col})" : "DATE_FORMAT({$col}, '%Y-%m-%d %H:00:00')";
    }

    /** Hora como texto 'YYYY-MM-DD HH:00' (para agrupar y comparar con claves generadas en PHP). */
    public static function horaTexto(string $col): string
    {
        return self::esPgsql() ? "to_char({$col}, 'YYYY-MM-DD HH24:00')" : "DATE_FORMAT({$col}, '%Y-%m-%d %H:00')";
    }

    /** Operador LIKE sin distinguir mayúsculas (MySQL ya lo hace con su collation; PostgreSQL usa ILIKE). */
    public static function like(): string
    {
        return self::esPgsql() ? 'ilike' : 'like';
    }

    /**
     * Cláusula de UPSERT para INSERT … SELECT / VALUES: actualiza $columnas cuando choca la llave $claves.
     */
    public static function alDuplicar(array $claves, array $columnas): string
    {
        if (self::esPgsql()) {
            return 'ON CONFLICT (' . implode(', ', $claves) . ') DO UPDATE SET '
                . implode(', ', array_map(fn ($c) => "{$c} = EXCLUDED.{$c}", $columnas));
        }
        return 'ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn ($c) => "{$c} = VALUES({$c})", $columnas));
    }
}
