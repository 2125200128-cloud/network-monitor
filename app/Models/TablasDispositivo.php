<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TablasDispositivo extends Model
{
    protected $table = 'tablas_dispositivo';

    protected $fillable = [
        'dispositivo_id',
        'mac_table',
        'arp_table',
        'lldp_neighbors',
        'vlans_table',
        'spanning_tree',
        'lacp_port_channels',
        'security_qos_multicast',
    ];

    protected $casts = [
        'mac_table' => 'array',
        'arp_table' => 'array',
        'lldp_neighbors' => 'array',
        'vlans_table' => 'array',
        'spanning_tree' => 'array',
        'lacp_port_channels' => 'array',
        'security_qos_multicast' => 'array',
    ];

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class);
    }
}
