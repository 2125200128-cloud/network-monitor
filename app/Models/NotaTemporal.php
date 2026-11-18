<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nota temporal del portapapeles del NOC (página Notas): caduca y se borra sola. */
class NotaTemporal extends Model
{
    protected $table = 'notas_temporales';

    protected $guarded = ['id'];

    protected $casts = ['privada' => 'boolean', 'expira_en' => 'datetime'];

    /** Duraciones que se pueden elegir: clave => [etiqueta, minutos]. «fija» (sin caducidad) solo la usa un Admin. */
    public const DURACIONES = [
        '1h' => ['1 hora', 60],
        '8h' => ['8 horas', 480],
        '1d' => ['1 día', 1440],
        '7d' => ['7 días', 10080],
        'fija' => ['Nunca (nota fija, solo Admin)', null],
    ];

    public function esFija(): bool
    {
        return $this->expira_en === null;
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Las vigentes que puede ver el usuario: las compartidas y sus propias privadas. */
    public function scopeVisiblesPara(Builder $q, ?User $u): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('expira_en')->orWhere('expira_en', '>', now()))
            ->where(fn ($w) => $w->where('privada', false)->orWhere('user_id', $u?->id));
    }

    /**
     * Bloques para copiar uno por uno: el texto separado por líneas en blanco (p. ej. un comando por bloque).
     * Si solo hay un bloque no se parte.
     */
    public function bloques(): array
    {
        $bloques = array_values(array_filter(array_map('trim', preg_split('/\R[ \t]*\R/u', $this->contenido)), fn ($b) => $b !== ''));
        return count($bloques) > 1 ? $bloques : [trim($this->contenido)];
    }
}
