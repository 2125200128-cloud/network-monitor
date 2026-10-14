<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Acomodo compartido del mapa de topología (una sola fila).
 */
class TopologiaPlano extends Model
{
    protected $table = 'topologia_plano';

    protected $fillable = ['posiciones', 'updated_by'];

    protected $casts = [
        'posiciones' => 'array',
    ];

    public static function actual(): self
    {
        return static::query()->first() ?? static::create(['posiciones' => []]);
    }
}
