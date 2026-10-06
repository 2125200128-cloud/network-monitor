<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicioWeb extends Model
{
    use HasFactory;

    protected $table = 'servicios_web';

    protected $fillable = [
        'nombre',
        'url',
        'metodo',
        'categoria',
        'estado',
        'codigo_http',
        'tiempo_respuesta_ms',
        'ssl_valido',
        'ssl_dias_expiracion',
        'detalles_error',
        'ultimo_chequeo',
        'es_activo'
    ];

    protected $casts = [
        'es_activo' => 'boolean',
        'ssl_valido' => 'boolean',
        'tiempo_respuesta_ms' => 'float',
        'codigo_http' => 'integer',
        'ssl_dias_expiracion' => 'integer',
        'ultimo_chequeo' => 'datetime',
    ];

    public function historico()
    {
        return $this->hasMany(HistoricoServicioWeb::class, 'servicio_web_id');
    }
}
