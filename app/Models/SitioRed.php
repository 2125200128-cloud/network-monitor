<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SitioRed extends Model
{
    protected $table = 'sitios_red';

    protected $fillable = ['origen', 'origen_ref', 'nombre', 'categoria', 'direccion', 'municipio', 'operador', 'lat', 'lon'];

    protected $casts = ['lat' => 'float', 'lon' => 'float'];

    public const CATEGORIAS = ['recaudadora', 'gobierno', 'ayuntamiento', 'justicia', 'seguridad', 'salud', 'educacion'];

    public function dispositivos(): HasMany
    {
        return $this->hasMany(Dispositivo::class, 'sitio_id');
    }
}
