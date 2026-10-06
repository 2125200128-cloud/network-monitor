<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoricoServicioWeb extends Model
{
    use HasFactory;

    protected $table = 'historico_servicio_web';

    public $timestamps = false;

    protected $fillable = [
        'servicio_web_id',
        'estado',
        'codigo_http',
        'tiempo_respuesta_ms',
        'detalles_error',
        'created_at'
    ];

    protected $casts = [
        'tiempo_respuesta_ms' => 'float',
        'codigo_http' => 'integer',
        'created_at' => 'datetime',
    ];

    public function servicioWeb()
    {
        return $this->belongsTo(ServicioWeb::class, 'servicio_web_id');
    }
}
