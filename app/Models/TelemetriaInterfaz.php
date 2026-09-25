<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetriaInterfaz extends Model
{
    protected $table = 'telemetria_interfaces';

    protected $fillable = [
        'interfaz_id',
        'oper_status',
        'in_octets',
        'out_octets',
        'in_unicast_pkts',
        'out_unicast_pkts',
        'in_multicast_pkts',
        'out_multicast_pkts',
        'in_broadcast_pkts',
        'out_broadcast_pkts',
        'bandwidth_util_pct',
        'last_change_str',
        'in_errors',
        'out_errors',
        'in_discards',
        'out_discards',
        'crc_errors',
        'collisions',
        'runts',
        'giants',
        'jabbers',
        'alignment_errors',
        'fcs_errors',
        'optica_rx_dbm',
        'optica_tx_dbm',
        'optica_temp_c',
        'optica_voltage_v',
        'optica_bias_ma',
        'optica_tx_fault',
        'poe_watts_consumo',
        'poe_class',
        'poe_status',
        'poe_voltage_v',
        'poe_max_watts',
        'is_errdisabled',
        'errdisabled_reason',
    ];

    protected $casts = [
        'optica_tx_fault' => 'boolean',
        'is_errdisabled' => 'boolean',
        'bandwidth_util_pct' => 'float',
        'poe_watts_consumo' => 'float',
    ];

    public function interfaz()
    {
        return $this->belongsTo(InterfazRed::class, 'interfaz_id');
    }
}
