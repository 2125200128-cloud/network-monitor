<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlarmaRed extends Model
{
    use HasFactory;

    protected $table = 'alarmas_red';

    protected $fillable = [
        'clave',
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
        'reconocido_por',
        'notificada_telegram_at',
        'ip',
        'recordada_telegram_at',
        'recordatorios',
        'notificada_correo_at',
        'recordada_correo_at',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_resolucion' => 'datetime',
        'notificada_telegram_at' => 'datetime',
        'recordada_telegram_at' => 'datetime',
        'notificada_correo_at' => 'datetime',
        'recordada_correo_at' => 'datetime',
    ];

    /** IP de la alarma: la guardada, la del equipo o la que venga en el título («Equipo inaccesible (10.4.2.1)»). */
    public function ipDe(): ?string
    {
        if ($this->ip) {
            return $this->ip;
        }
        if ($this->dispositivo?->ip) {
            return $this->dispositivo->ip;
        }
        return preg_match('/(?<![\d.])(\d{1,3}(?:\.\d{1,3}){3})(?![\d.])/', (string) $this->titulo, $m) ? $m[1] : null;
    }

    /** ¿Es de la red prioritaria (Configuración → Notificaciones, por omisión 10.4.0.0/16)? */
    public function esPrioritaria(): bool
    {
        return \App\Support\RedesPrioritarias::contiene($this->ipDe());
    }

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
