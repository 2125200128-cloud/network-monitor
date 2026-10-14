<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Recuadro con nombre en el mapa (edificio, datacenter, sitio...), en coordenadas del lienzo.
 */
class ZonaTopologia extends Model
{
    protected $table = 'zonas_topologia';

    protected $fillable = ['nombre', 'color', 'rangos_ip', 'x', 'y', 'ancho', 'alto', 'created_by'];

    protected $casts = [
        'x' => 'float',
        'y' => 'float',
        'ancho' => 'float',
        'alto' => 'float',
    ];
}
