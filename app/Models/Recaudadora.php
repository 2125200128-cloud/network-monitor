<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Oficina recaudadora (u otra oficina de Hacienda) con sus enlaces. Estado que deja recaudadoras:revisar:
 *   online   la IP administrativa (puerta de enlace de su red) responde
 *   lan      no responde la IP administrativa pero sí la loopback: el equipo está, falla su red interna
 *   offline  no responde ninguna: sin comunicación
 */
class Recaudadora extends Model
{
    protected $table = 'recaudadoras';

    protected $guarded = ['id'];

    protected $casts = [
        'activo' => 'boolean',
        'admin_ok' => 'boolean',
        'loopback_ok' => 'boolean',
        'admin_alguna_vez' => 'boolean',
        'loopback_alguna_vez' => 'boolean',
        'estado_desde' => 'datetime',
        'ultimo_chequeo' => 'datetime',
        'latencia_ms' => 'float',
        'ruta' => 'array',
        'ruta_medida_en' => 'datetime',
    ];

    public const TIPOS = ['recaudadora' => 'Recaudadora', 'oficina' => 'Otra oficina'];

    /** Datos de enlace y proveedores: campo → [etiqueta, largo máximo]. Los usan la ficha, el formulario y la importación. */
    public const CAMPOS_ENLACE = [
        'tipo_enlace' => ['Tipo de enlace', 40],
        'uninet' => ['Uninet (Telmex)', 120],
        'referencia' => ['Referencia Uninet', 80],
        'id_sitio_red_jalisco' => ['ID sitio Red Jalisco', 60],
        'velocidad_red_jalisco' => ['Velocidad Red Jalisco', 60],
        'bestel' => ['Bestel', 255],
        'bestel_referencia' => ['Referencia Bestel', 80],
        'global_king' => ['Global King', 60],
        'enlace_backup' => ['Enlace backup', 80],
        'gobierno_estado' => ['Gobierno del Estado', 60],
        'hemac' => ['HEMAC', 60],
        'numero_referencia' => ['Número de referencia', 120],
    ];

    /** Etiqueta legible de cualquier campo editable (avisos de la importación, bitácora). */
    public static function etiquetaCampo(string $campo): string
    {
        return self::CAMPOS_ENLACE[$campo][0] ?? match ($campo) {
            'numero' => 'Número', 'nombre' => 'Nombre', 'coordinadora' => 'Coordinadora', 'tipo' => 'Tipo',
            'ip_administrativa' => 'IP administrativa', 'ip_loopback' => 'IP loopback', 'municipio_cve' => 'Municipio',
            'notas' => 'Notas', 'activo' => 'Monitorear',
            default => $campo,
        };
    }

    /** Clave única de una recaudadora por su número: «R-» y el número sin ceros a la izquierda («002A» → «R-2A»). */
    public static function claveDeNumero(string $numero): string
    {
        return 'R-' . preg_replace('/^0+(?=\d)/', '', strtoupper(trim($numero)));
    }

    public const ESTADOS = [
        'online' => ['etiqueta' => 'En servicio', 'color' => '#059669'],
        'lan' => ['etiqueta' => 'Falla de red interna', 'color' => '#d97706'],
        'offline' => ['etiqueta' => 'Sin comunicación', 'color' => '#dc2626'],
        'sin_ip' => ['etiqueta' => 'Sin IP para monitorear', 'color' => '#94a3b8'],
        'pendiente' => ['etiqueta' => 'Aún sin revisar', 'color' => '#64748b'],
    ];

    public function equipos(): HasMany
    {
        return $this->hasMany(RecaudadoraEquipo::class)->orderBy('tipo')->orderBy('nombre');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(RecaudadoraEvento::class)->orderByDesc('inicio');
    }

    /** Estado para mostrar: distingue las que no tienen IP y las que el temporizador no ha revisado hace rato. */
    public function estadoVisible(): string
    {
        if (!$this->ip_administrativa && !$this->ip_loopback) {
            return 'sin_ip';
        }
        if (!$this->estado || !$this->ultimo_chequeo || $this->ultimo_chequeo->lt(now()->subMinutes(5))) {
            return 'pendiente';
        }
        return $this->estado;
    }

    /** Nombre corto para listas y avisos: «Reca 8 · Atemajac de Brizuela». */
    public function titulo(): string
    {
        return $this->tipo === 'recaudadora' && $this->numero !== null && $this->numero !== ''
            ? "Reca {$this->numero} · {$this->nombre}"
            : $this->nombre;
    }

    /** Proveedores con servicio en la recaudadora, según las columnas del Excel que traen dato. */
    public function proveedores(): array
    {
        return array_keys(array_filter([
            'Uninet (Telmex)' => $this->uninet || $this->referencia,
            'Red Jalisco' => $this->id_sitio_red_jalisco || $this->velocidad_red_jalisco,
            'Bestel' => $this->bestel,
            'Global King' => $this->global_king,
            'Gobierno del Estado' => $this->gobierno_estado,
            'HEMAC' => $this->hemac,
        ]));
    }
}
