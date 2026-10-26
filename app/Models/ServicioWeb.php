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
        'es_activo',
        'fallos_consecutivos',
        'lentos_consecutivos',
        'latencia_umbral_ms',
        'latencia_base_ms',
        'texto_esperado',
        'tiempo_dns_ms',
        'tiempo_conexion_ms',
        'tiempo_tls_ms',
        'tiempo_servidor_ms',
        'ssl_problema',
        'ip_servidor',
        'vms_manual',
    ];

    protected $casts = [
        'es_activo' => 'boolean',
        'ssl_valido' => 'boolean',
        'tiempo_respuesta_ms' => 'float',
        'codigo_http' => 'integer',
        'ssl_dias_expiracion' => 'integer',
        'ultimo_chequeo' => 'datetime',
        'fallos_consecutivos' => 'integer',
        'lentos_consecutivos' => 'integer',
        'latencia_umbral_ms' => 'integer',
        'latencia_base_ms' => 'float',
        'tiempo_dns_ms' => 'float',
        'tiempo_conexion_ms' => 'float',
        'tiempo_tls_ms' => 'float',
        'tiempo_servidor_ms' => 'float',
        'vms_manual' => 'array',
    ];

    /**
     * Latencia (ms) a partir de la cual el sitio se considera lento: la manual si se configuró; si no,
     * el doble de su tiempo normal (p95 de la última semana), entre 1.5 s y casi el tiempo de espera.
     */
    public function umbralLatenciaMs(): int
    {
        if ($this->latencia_umbral_ms) {
            return (int) $this->latencia_umbral_ms;
        }
        if ($this->latencia_base_ms) {
            return (int) min(\App\Services\WebServicePoller::UMBRAL_AUTO_MAX_MS,
                max(\App\Services\WebServicePoller::UMBRAL_AUTO_MIN_MS, round($this->latencia_base_ms * 2)));
        }
        return \App\Services\WebServicePoller::LATENCIA_WARNING_MS;
    }

    public function historico()
    {
        return $this->hasMany(HistoricoServicioWeb::class, 'servicio_web_id');
    }
}
