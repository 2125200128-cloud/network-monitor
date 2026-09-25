<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnlaceRed extends Model
{
    use HasFactory;

    protected $table = 'enlaces_red';

    protected $fillable = [
        'origen_dispositivo_id',
        'origen_interfaz_id',
        'destino_dispositivo_id',
        'destino_interfaz_id',
        'tipo_medio',
        'estado',
        'velocidad_mbps',
        'trafico_in_mbps',
        'trafico_out_mbps',
        'vlans_permitidas',
    ];

    protected $casts = [
        'velocidad_mbps' => 'integer',
        'trafico_in_mbps' => 'float',
        'trafico_out_mbps' => 'float',
    ];

    public function dispositivoOrigen()
    {
        return $this->belongsTo(Dispositivo::class, 'origen_dispositivo_id');
    }

    public function dispositivoDestino()
    {
        return $this->belongsTo(Dispositivo::class, 'destino_dispositivo_id');
    }

    public function interfazOrigen()
    {
        return $this->belongsTo(InterfazRed::class, 'origen_interfaz_id');
    }

    public function interfazDestino()
    {
        return $this->belongsTo(InterfazRed::class, 'destino_interfaz_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'up');
    }

    /**
     * Calcula el porcentaje de saturación respecto a la velocidad máxima negociada
     */
    public function getPorcentajeSaturacionAttribute()
    {
        $maxTrafico = max($this->trafico_in_mbps, $this->trafico_out_mbps);
        if ($this->velocidad_mbps > 0) {
            return round(($maxTrafico / $this->velocidad_mbps) * 100, 1);
        }
        return 0;
    }
}
