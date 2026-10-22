<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Equipo físico del centro de datos; las filas con el mismo `nodo` son un par HA o un stack. */
class InventarioCentroDatos extends Model
{
    protected $table = 'inventario_centro_datos';

    protected $fillable = ['nodo', 'capa', 'orden', 'rol', 'marca', 'modelo', 'serie', 'descripcion', 'version_os', 'ip', 'hostname'];
}
