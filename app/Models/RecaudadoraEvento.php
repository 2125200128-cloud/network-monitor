<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Caída de una recaudadora (sin comunicación o falla de red interna) o de uno de sus equipos. fin = null: sigue abierta. */
class RecaudadoraEvento extends Model
{
    protected $table = 'recaudadora_eventos';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['inicio' => 'datetime', 'fin' => 'datetime'];

    public const TIPOS = [
        'sin_comunicacion' => 'Sin comunicación',
        'lan' => 'Falla de red interna',
        'equipo' => 'Equipo caído',
    ];

    public function recaudadora(): BelongsTo
    {
        return $this->belongsTo(Recaudadora::class);
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(RecaudadoraEquipo::class, 'equipo_id');
    }

    public function duracionSegundos(): int
    {
        return (int) $this->inicio->diffInSeconds($this->fin ?? now(), true);
    }
}
