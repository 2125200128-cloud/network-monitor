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
        'comunidad_snmp_firewall',
        'ip_switch_core',
        'umbral_cpu_warning',
        'umbral_loss_warning',
        'retencion_metricas_dias',
        'retencion_tendencias_dias',
        'retencion_consolidado_dias',
        'retencion_alarmas_dias',
        'retencion_servicios_web_dias',
        'telegram_activo',
        'telegram_bot_token',
        'telegram_chat_id',
        'telegram_severidad_min',
        'telegram_resueltas',
        'telegram_imagenes',
    ];

    protected $hidden = ['telegram_bot_token'];

    protected $casts = [
        'telegram_activo' => 'boolean',
        'telegram_bot_token' => 'encrypted',
        'telegram_resueltas' => 'boolean',
        'telegram_imagenes' => 'boolean',
    ];

    /**
     * Retención editable: clave de config/noc.php retencion_dias => [columna, mínimo, máximo].
     * Los mínimos protegen las ventanas que usan las pantallas (tendencias 7 d, disponibilidad 30 d).
     */
    public const RETENCION = [
        'metricas_red' => ['retencion_metricas_dias', 1, 90],
        'global_metrics' => ['retencion_tendencias_dias', 7, 365],
        'metricas_red_hora' => ['retencion_consolidado_dias', 30, 1825],
        'alarmas_resueltas' => ['retencion_alarmas_dias', 7, 1825],
        'historico_servicio_web' => ['retencion_servicios_web_dias', 1, 90],
    ];

    /** Días efectivos por tabla: lo guardado en Configuración o, si está vacío, el valor de config/noc.php. */
    public static function retencionDias(): array
    {
        $fila = static::query()->first();
        $dias = [];
        foreach (self::RETENCION as $clave => [$columna, $min, $max]) {
            $valor = $fila?->{$columna} ?? config("noc.retencion_dias.{$clave}");
            $dias[$clave] = min($max, max($min, (int) $valor));
        }
        return $dias;
    }
}
