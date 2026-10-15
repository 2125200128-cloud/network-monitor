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
        'stp_cambios_topologia',
        'stp_seg_desde_cambio',
        'vpn_tuneles',
        'ha_estado',
        'wifi_aps',
        'wifi_clientes',
        'detalle_medido_en',
        'forti_sistema',
        'forti_sensores',
        'forti_sdwan',
        'forti_vpn_ssl',
        'forti_seguridad',
    ];

    protected $casts = [
        'estado_fuentes' => 'array',
        'estado_ventiladores' => 'array',
        'sensores_temperatura' => 'array',
        'cpu_cores' => 'array',
        'vpn_tuneles' => 'array',
        'ha_estado' => 'array',
        'wifi_aps' => 'array',
        'detalle_medido_en' => 'datetime',
        'forti_sistema' => 'array',
        'forti_sensores' => 'array',
        'forti_sdwan' => 'array',
        'forti_vpn_ssl' => 'array',
        'forti_seguridad' => 'array',
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
