<?php

namespace App\Support;

/**
 * Fragmentos de SQL de PostgreSQL que se repiten en las consultas crudas del proyecto.
 */
class Sql
{
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
        return "EXTRACT(EPOCH FROM ({$b} - {$a}))";
    }

    /** Segundos desde 1970 de una columna de fecha. */
    public static function epoch(string $col): string
    {
        return "EXTRACT(EPOCH FROM {$col})";
    }

    /** Inicio de la hora de una fecha, como fecha (para guardar en columnas TIMESTAMP). */
    public static function inicioDeHora(string $col): string
    {
        return "date_trunc('hour', {$col})";
    }

    /** Hora como texto 'YYYY-MM-DD HH:00' (para agrupar y comparar con claves generadas en PHP). */
    public static function horaTexto(string $col): string
    {
        return "to_char({$col}, 'YYYY-MM-DD HH24:00')";
    }

    /** Operador LIKE sin distinguir mayúsculas. */
    public static function like(): string
    {
        return 'ilike';
    }

    /**
     * Cláusula de UPSERT para INSERT … SELECT / VALUES: actualiza $columnas cuando choca la llave $claves.
     */
    public static function alDuplicar(array $claves, array $columnas): string
    {
        return 'ON CONFLICT (' . implode(', ', $claves) . ') DO UPDATE SET '
            . implode(', ', array_map(fn ($c) => "{$c} = EXCLUDED.{$c}", $columnas));
    }
}
