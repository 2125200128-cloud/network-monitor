<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetricaRed extends Model
{
    use HasFactory;

    protected $table = 'metricas_red';
    
    public $timestamps = false; // We use fecha_registro instead of created_at/updated_at
    const CREATED_AT = 'fecha_registro';


    protected $fillable = [
        'dispositivo_id',
        'bytes_in',
        'bytes_out',
        'ping_ms',
        'cpu_usage',
        'memory_usage',
        'packet_loss',
        'uptime',
        'temperatura_celsius',
        'conexiones_activas',
        'errores_interfaz',
        'fecha_registro'
    ];

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class);
    }
}
