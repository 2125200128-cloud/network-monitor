<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispositivo extends Model
{
    use HasFactory;

    protected $table = 'dispositivos';

    protected $fillable = [
        'nombre',
        'ip',
        'mac_address',
        'comunidad_snmp',
        'ubicacion',
        'estado',
        'ultima_vez_visto',
        'ssh_user',
        'ssh_password_encrypted',
        'ssh_port'
    ];

    public function metricas()
    {
        return $this->hasMany(MetricaRed::class);
    }

    public function ultimaMetrica()
    {
        return $this->hasOne(MetricaRed::class, 'dispositivo_id')->latestOfMany('fecha_registro');
    }

    public function auditoriaComandos()
    {
        return $this->hasMany(AuditoriaComando::class);
    }

    public function configuraciones()
    {
        return $this->hasMany(ConfiguracionDispositivo::class);
    }

    public function interfaces()
    {
        return $this->hasMany(InterfazRed::class, 'dispositivo_id');
    }

    public function telemetriaChasis()
    {
        return $this->hasOne(TelemetriaChasis::class, 'dispositivo_id');
    }

    public function tablasDispositivo()
    {
        return $this->hasOne(TablasDispositivo::class, 'dispositivo_id');
    }

    public function enlacesOrigen()
    {
        return $this->hasMany(EnlaceRed::class, 'origen_dispositivo_id');
    }

    public function enlacesDestino()
    {
        return $this->hasMany(EnlaceRed::class, 'destino_dispositivo_id');
    }
}
