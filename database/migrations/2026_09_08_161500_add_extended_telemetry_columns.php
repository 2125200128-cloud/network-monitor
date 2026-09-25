<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Interfaces Red
        Schema::table('interfaces_red', function (Blueprint $table) {
            if (!Schema::hasColumn('interfaces_red', 'duplex')) {
                $table->string('duplex')->default('Full')->after('velocidad_mbps');
            }
            if (!Schema::hasColumn('interfaces_red', 'autoneg')) {
                $table->boolean('autoneg')->default(true)->after('duplex');
            }
            if (!Schema::hasColumn('interfaces_red', 'port_type')) {
                $table->string('port_type')->default('copper')->after('autoneg'); // copper, sfp, sfp_plus
            }
            if (!Schema::hasColumn('interfaces_red', 'alias')) {
                $table->string('alias')->nullable()->after('port_type'); // Port description
            }
            if (!Schema::hasColumn('interfaces_red', 'mode')) {
                $table->string('mode')->default('access')->after('vlan_id'); // access, trunk, routed, port-channel
            }
            if (!Schema::hasColumn('interfaces_red', 'port_channel')) {
                $table->string('port_channel')->nullable()->after('mode'); // e.g. Po1
            }
        });

        // 2. Telemetria Interfaces
        Schema::table('telemetria_interfaces', function (Blueprint $table) {
            // Contadores 64-bit
            if (!Schema::hasColumn('telemetria_interfaces', 'in_unicast_pkts')) {
                $table->unsignedBigInteger('in_unicast_pkts')->default(0)->after('out_octets');
                $table->unsignedBigInteger('out_unicast_pkts')->default(0)->after('in_unicast_pkts');
                $table->unsignedBigInteger('in_multicast_pkts')->default(0)->after('out_unicast_pkts');
                $table->unsignedBigInteger('out_multicast_pkts')->default(0)->after('in_multicast_pkts');
                $table->unsignedBigInteger('in_broadcast_pkts')->default(0)->after('out_multicast_pkts');
                $table->unsignedBigInteger('out_broadcast_pkts')->default(0)->after('in_broadcast_pkts');
            }
            if (!Schema::hasColumn('telemetria_interfaces', 'bandwidth_util_pct')) {
                $table->decimal('bandwidth_util_pct', 5, 2)->default(0)->after('out_broadcast_pkts');
            }
            if (!Schema::hasColumn('telemetria_interfaces', 'last_change_str')) {
                $table->string('last_change_str')->nullable()->after('bandwidth_util_pct');
            }

            // Errores especificos
            if (!Schema::hasColumn('telemetria_interfaces', 'crc_errors')) {
                $table->unsignedBigInteger('crc_errors')->default(0)->after('out_discards');
                $table->unsignedBigInteger('collisions')->default(0)->after('crc_errors');
                $table->unsignedBigInteger('runts')->default(0)->after('collisions');
                $table->unsignedBigInteger('giants')->default(0)->after('runts');
                $table->unsignedBigInteger('jabbers')->default(0)->after('giants');
                $table->unsignedBigInteger('alignment_errors')->default(0)->after('jabbers');
                $table->unsignedBigInteger('fcs_errors')->default(0)->after('alignment_errors');
            }

            // Optica SFP (DOM)
            if (!Schema::hasColumn('telemetria_interfaces', 'optica_temp_c')) {
                $table->decimal('optica_temp_c', 5, 2)->nullable()->after('optica_tx_dbm');
                $table->decimal('optica_voltage_v', 5, 2)->nullable()->after('optica_temp_c');
                $table->decimal('optica_bias_ma', 5, 2)->nullable()->after('optica_voltage_v');
                $table->boolean('optica_tx_fault')->default(false)->after('optica_bias_ma');
            }

            // PoE
            if (!Schema::hasColumn('telemetria_interfaces', 'poe_class')) {
                $table->string('poe_class')->nullable()->after('poe_watts_consumo'); // Class 4 (PoE+), Class 3, etc.
                $table->string('poe_status')->default('Disabled')->after('poe_class'); // Delivering, Searching, Disabled
                $table->decimal('poe_voltage_v', 5, 2)->nullable()->after('poe_status');
                $table->decimal('poe_max_watts', 5, 2)->nullable()->after('poe_voltage_v');
            }

            // Err-disabled
            if (!Schema::hasColumn('telemetria_interfaces', 'is_errdisabled')) {
                $table->boolean('is_errdisabled')->default(false)->after('poe_max_watts');
                $table->string('errdisabled_reason')->nullable()->after('is_errdisabled'); // bpduguard, loopback, port-security, etc.
            }
        });

        // 3. Telemetria Chasis
        Schema::table('telemetria_chasis', function (Blueprint $table) {
            if (!Schema::hasColumn('telemetria_chasis', 'cpu_cores')) {
                $table->json('cpu_cores')->nullable()->after('cpu_utilization');
            }
            if (!Schema::hasColumn('telemetria_chasis', 'ram_total_mb')) {
                $table->unsignedInteger('ram_total_mb')->default(4096)->after('ram_utilization');
                $table->unsignedInteger('ram_used_mb')->default(1638)->after('ram_total_mb');
            }
            if (!Schema::hasColumn('telemetria_chasis', 'sensores_temperatura')) {
                $table->json('sensores_temperatura')->nullable()->after('temperatura_c');
            }
            if (!Schema::hasColumn('telemetria_chasis', 'uptime_str')) {
                $table->string('uptime_str')->nullable()->after('ram_used_mb');
                $table->string('os_version')->nullable()->after('uptime_str');
                $table->string('serial_number')->nullable()->after('os_version');
                $table->string('model_name')->nullable()->after('serial_number');
            }
            if (!Schema::hasColumn('telemetria_chasis', 'poe_total_budget_w')) {
                $table->decimal('poe_total_budget_w', 7, 2)->default(740.0)->after('model_name');
                $table->decimal('poe_used_w', 7, 2)->default(0.0)->after('poe_total_budget_w');
                $table->decimal('poe_available_w', 7, 2)->default(740.0)->after('poe_used_w');
            }
        });

        // 4. Tablas Dispositivo
        Schema::table('tablas_dispositivo', function (Blueprint $table) {
            if (!Schema::hasColumn('tablas_dispositivo', 'vlans_table')) {
                $table->json('vlans_table')->nullable()->after('lldp_neighbors');
            }
            if (!Schema::hasColumn('tablas_dispositivo', 'spanning_tree')) {
                $table->json('spanning_tree')->nullable()->after('vlans_table');
            }
            if (!Schema::hasColumn('tablas_dispositivo', 'lacp_port_channels')) {
                $table->json('lacp_port_channels')->nullable()->after('spanning_tree');
            }
            if (!Schema::hasColumn('tablas_dispositivo', 'security_qos_multicast')) {
                $table->json('security_qos_multicast')->nullable()->after('lacp_port_channels');
            }
        });
    }

    public function down(): void
    {
        Schema::table('interfaces_red', function (Blueprint $table) {
            $table->dropColumn(['duplex', 'autoneg', 'port_type', 'alias', 'mode', 'port_channel']);
        });

        Schema::table('telemetria_interfaces', function (Blueprint $table) {
            $table->dropColumn([
                'in_unicast_pkts', 'out_unicast_pkts', 'in_multicast_pkts', 'out_multicast_pkts',
                'in_broadcast_pkts', 'out_broadcast_pkts', 'bandwidth_util_pct', 'last_change_str',
                'crc_errors', 'collisions', 'runts', 'giants', 'jabbers', 'alignment_errors', 'fcs_errors',
                'optica_temp_c', 'optica_voltage_v', 'optica_bias_ma', 'optica_tx_fault',
                'poe_class', 'poe_status', 'poe_voltage_v', 'poe_max_watts',
                'is_errdisabled', 'errdisabled_reason'
            ]);
        });

        Schema::table('telemetria_chasis', function (Blueprint $table) {
            $table->dropColumn([
                'cpu_cores', 'ram_total_mb', 'ram_used_mb', 'sensores_temperatura',
                'uptime_str', 'os_version', 'serial_number', 'model_name',
                'poe_total_budget_w', 'poe_used_w', 'poe_available_w'
            ]);
        });

        Schema::table('tablas_dispositivo', function (Blueprint $table) {
            $table->dropColumn(['vlans_table', 'spanning_tree', 'lacp_port_channels', 'security_qos_multicast']);
        });
    }
};
