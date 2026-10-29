<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Métricas extendidas del dashboard NOC.
 *
 *  - Los contadores que ningún worker recolectaba (CRC/FCS, broadcast, err-disabled, falla TX óptica)
 *    tenían 0/false por defecto: pasan a NULL ("sin dato") hasta que snmp_detail_collector.py los mida.
 *  - Nuevas columnas medidas por SNMP: STP, túneles VPN y HA de FortiGate, APs/clientes del WLC,
 *    tasa de broadcast por puerto.
 *  - alarmas_red.clave: identificador estable de la alarma (p.ej. "off-12") para llevar su historial.
 *  - metricas_red_hora: consolidado horario de metricas_red para disponibilidad 24 h / 7 d / 30 d.
 */
return new class extends Migration
{
    private array $contadores = [
        'in_unicast_pkts', 'out_unicast_pkts', 'in_multicast_pkts', 'out_multicast_pkts',
        'in_broadcast_pkts', 'out_broadcast_pkts', 'crc_errors', 'collisions', 'runts',
        'giants', 'jabbers', 'alignment_errors', 'fcs_errors',
    ];

    public function up(): void
    {
        Schema::table('telemetria_interfaces', function (Blueprint $table) {
            foreach ($this->contadores as $col) {
                $table->unsignedBigInteger($col)->nullable()->default(null)->change();
            }
            $table->boolean('is_errdisabled')->nullable()->default(null)->change();
            $table->boolean('optica_tx_fault')->nullable()->default(null)->change();
            if (!Schema::hasColumn('telemetria_interfaces', 'broadcast_pps')) {
                $table->decimal('broadcast_pps', 12, 2)->nullable()->after('out_broadcast_pkts');
            }
        });

        $nulos = array_fill_keys($this->contadores, null);
        DB::table('telemetria_interfaces')->update($nulos + ['is_errdisabled' => null, 'optica_tx_fault' => null]);

        Schema::table('telemetria_chasis', function (Blueprint $table) {
            if (!Schema::hasColumn('telemetria_chasis', 'stp_cambios_topologia')) {
                $table->unsignedInteger('stp_cambios_topologia')->nullable()->after('poe_available_w');
                $table->unsignedInteger('stp_seg_desde_cambio')->nullable()->after('stp_cambios_topologia');
                $table->json('vpn_tuneles')->nullable()->after('stp_seg_desde_cambio');
                $table->json('ha_estado')->nullable()->after('vpn_tuneles');
                $table->json('wifi_aps')->nullable()->after('ha_estado');
                $table->unsignedInteger('wifi_clientes')->nullable()->after('wifi_aps');
                $table->timestamp('detalle_medido_en')->nullable()->after('wifi_clientes');
            }
        });

        Schema::table('alarmas_red', function (Blueprint $table) {
            if (!Schema::hasColumn('alarmas_red', 'clave')) {
                $table->string('clave', 120)->nullable()->after('id')->index();
            }
        });

        if (!Schema::hasTable('metricas_red_hora')) {
            Schema::create('metricas_red_hora', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
                $table->dateTime('hora');
                $table->unsignedInteger('muestras');
                $table->unsignedInteger('muestras_up');
                $table->float('ping_avg', 8, 2)->nullable();
                $table->float('ping_std', 8, 2)->nullable();
                $table->float('ping_max', 8, 2)->nullable();
                $table->float('loss_avg', 5, 2)->nullable();
                $table->float('cpu_avg', 5, 2)->nullable();
                $table->unsignedTinyInteger('cpu_max')->nullable();
                $table->float('mem_avg', 5, 2)->nullable();
                $table->unique(['dispositivo_id', 'hora'], 'uq_metrica_hora');
                $table->index('hora');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('metricas_red_hora');
        Schema::table('alarmas_red', function (Blueprint $table) {
            if (Schema::hasColumn('alarmas_red', 'clave')) {
                $table->dropColumn('clave');
            }
        });
        Schema::table('telemetria_chasis', function (Blueprint $table) {
            if (Schema::hasColumn('telemetria_chasis', 'stp_cambios_topologia')) {
                $table->dropColumn(['stp_cambios_topologia', 'stp_seg_desde_cambio', 'vpn_tuneles', 'ha_estado',
                    'wifi_aps', 'wifi_clientes', 'detalle_medido_en']);
            }
        });
        Schema::table('telemetria_interfaces', function (Blueprint $table) {
            if (Schema::hasColumn('telemetria_interfaces', 'broadcast_pps')) {
                $table->dropColumn('broadcast_pps');
            }
        });
    }
};
