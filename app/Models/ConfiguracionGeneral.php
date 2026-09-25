<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionGeneral extends Model
{
    use HasFactory;

    protected $table = 'configuraciones_generales';

    protected $fillable = [
        'intervalo_sondeo_segundos',
        'comunidad_snmp_default',
        'ip_switch_core',
        'umbral_cpu_warning',
        'umbral_loss_warning',
    ];
}
