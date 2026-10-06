<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlarmaRed extends Model
{
    use HasFactory;

    protected $table = 'alarmas_red';

    protected $fillable = [
        'dispositivo_id',
        'interfaz_id',
        'enlace_id',
        'codigo_evento',
        'severidad',
        'categoria',
        'titulo',
        'mensaje',
        'causa_raiz',
        'impacto',
        'accion_sugerida',
        'estado',
        'fecha_inicio',
        'fecha_resolucion',
        'reconocido_por'
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_resolucion' => 'datetime',
    ];

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class, 'dispositivo_id');
    }

    public function interfaz()
    {
        return $this->belongsTo(InterfazRed::class, 'interfaz_id');
    }

    public function enlace()
    {
        return $this->belongsTo(EnlaceRed::class, 'enlace_id');
    }
}
