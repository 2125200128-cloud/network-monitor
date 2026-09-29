<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterfazRed extends Model
{
    protected $table = 'interfaces_red';

    protected $fillable = [
        'dispositivo_id',
        'if_index',
        'nombre',
        'mac_address',
        'velocidad_mbps',
        'duplex',
        'autoneg',
        'port_type',
        'alias',
        'admin_status',
        'vlan_id',
        'mode',
        'port_channel',
        'is_poe',
    ];

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function telemetria()
    {
        return $this->hasMany(TelemetriaInterfaz::class, 'interfaz_id');
    }

    public function ultimaTelemetria()
    {
        return $this->hasOne(TelemetriaInterfaz::class, 'interfaz_id')->latestOfMany();
    }
}
