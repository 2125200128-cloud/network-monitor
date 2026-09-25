<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetriaChasis extends Model
{
    protected $table = 'telemetria_chasis';

    protected $fillable = [
        'dispositivo_id',
        'temperatura_c',
        'sensores_temperatura',
        'estado_fuentes',
        'estado_ventiladores',
        'cpu_utilization',
        'cpu_cores',
        'ram_utilization',
        'ram_total_mb',
        'ram_used_mb',
        'uptime_str',
        'os_version',
        'serial_number',
        'model_name',
        'poe_total_budget_w',
        'poe_used_w',
        'poe_available_w',
    ];

    protected $casts = [
        'estado_fuentes' => 'array',
        'estado_ventiladores' => 'array',
        'sensores_temperatura' => 'array',
        'cpu_cores' => 'array',
        'temperatura_c' => 'float',
        'cpu_utilization' => 'float',
        'ram_utilization' => 'float',
        'poe_total_budget_w' => 'float',
        'poe_used_w' => 'float',
        'poe_available_w' => 'float',
    ];

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class);
    }
}
