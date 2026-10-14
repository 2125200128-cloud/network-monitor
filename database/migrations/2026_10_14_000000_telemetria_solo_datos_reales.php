<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La telemetría debe reflejar únicamente lo que reportan los equipos.
 *
 *  - Las métricas desconocidas pasan a NULL en lugar de valores por defecto inventados
 *    (CPU 0, RAM 4096 MB, presupuesto PoE 740 W, etc.).
 *  - Se agregan columnas de tráfico real (Mbps de entrada/salida).
 *  - Se eliminan los datos simulados que generaban versiones anteriores de los workers.
 *  - Se garantiza una sola fila de estado por equipo/interfaz (llaves únicas) y se
 *    restauran las llaves foráneas que perdió tools/maintenance/batch_dedup.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------- Columnas que deben admitir "sin dato" ----------------
        Schema::table('metricas_red', function (Blueprint $table) {
            $table->tinyInteger('cpu_usage')->nullable()->default(null)->change();
            $table->tinyInteger('memory_usage')->nullable()->default(null)->change();
            $table->decimal('packet_loss', 5, 2)->nullable()->default(null)->change();
            $table->bigInteger('uptime')->nullable()->default(null)->change();
            $table->tinyInteger('temperatura_celsius')->nullable()->default(null)->change();
            $table->integer('conexiones_activas')->nullable()->default(null)->change();
            $table->integer('errores_interfaz')->nullable()->default(null)->change();
            if (!Schema::hasColumn('metricas_red', 'traffic_in_mbps')) {
                $table->decimal('traffic_in_mbps', 12, 2)->nullable()->after('bytes_out');
                $table->decimal('traffic_out_mbps', 12, 2)->nullable()->after('traffic_in_mbps');
            }
        });

        Schema::table('global_metrics', function (Blueprint $table) {
            $table->float('traffic_mbps', 12, 2)->nullable()->default(null)->change();
            $table->float('latency_ms', 8, 2)->nullable()->default(null)->change();
            $table->float('packet_loss_pct', 5, 2)->nullable()->default(null)->change();
            $table->float('cpu_avg_pct', 5, 2)->nullable()->default(null)->change();
            $table->float('ram_avg_pct', 5, 2)->nullable()->default(null)->change();
            $table->float('port_saturation_pct', 5, 2)->nullable()->default(null)->change();
            if (!Schema::hasColumn('global_metrics', 'traffic_in_mbps')) {
                $table->float('traffic_in_mbps', 12, 2)->nullable()->after('traffic_mbps');
                $table->float('traffic_out_mbps', 12, 2)->nullable()->after('traffic_in_mbps');
            }
        });

        Schema::table('telemetria_chasis', function (Blueprint $table) {
            $table->unsignedInteger('ram_total_mb')->nullable()->default(null)->change();
            $table->unsignedInteger('ram_used_mb')->nullable()->default(null)->change();
            $table->decimal('poe_total_budget_w', 7, 2)->nullable()->default(null)->change();
            $table->decimal('poe_used_w', 7, 2)->nullable()->default(null)->change();
            $table->decimal('poe_available_w', 7, 2)->nullable()->default(null)->change();
        });

        Schema::table('telemetria_interfaces', function (Blueprint $table) {
            $table->decimal('bandwidth_util_pct', 5, 2)->nullable()->default(null)->change();
            $table->string('poe_status')->nullable()->default(null)->change();
        });

        Schema::table('interfaces_red', function (Blueprint $table) {
            $table->string('duplex')->nullable()->default(null)->change();
            $table->boolean('autoneg')->nullable()->default(null)->change();
            $table->string('port_type')->nullable()->default(null)->change();
            $table->string('mode')->nullable()->default(null)->change();
        });

        // ---------------- Limpieza de datos simulados ----------------
        // Interfaces inventadas por snmp_detail_collector.py (MAC 00:1A:2B:3C:xx:xx)
        // y por el alta manual de dispositivos (MAC 00:1E:F7:28:44:xx, alias PORT-n).
        $fakeIfaces = DB::table('interfaces_red')
            ->where(function ($q) {
                $q->where('mac_address', 'like', '00:1A:2B:3C:%')
                  ->orWhere(function ($q2) {
                      $q2->where('mac_address', 'like', '00:1E:F7:28:44:%')
                         ->where(fn ($q3) => $q3->where('alias', 'like', 'PORT-%')->orWhere('alias', 'UPLINK-MANAGEMENT-PC'));
                  });
            })
            ->pluck('id');
        foreach ($fakeIfaces->chunk(1000) as $chunk) {
            DB::table('telemetria_interfaces')->whereIn('interfaz_id', $chunk)->delete();
            DB::table('enlaces_red')->whereIn('origen_interfaz_id', $chunk)->update(['origen_interfaz_id' => null]);
            DB::table('enlaces_red')->whereIn('destino_interfaz_id', $chunk)->update(['destino_interfaz_id' => null]);
            DB::table('interfaces_red')->whereIn('id', $chunk)->delete();
        }

        // Los crawlers escribían 'VLAN 1' fijo en todos los enlaces sin consultarlo
        DB::table('enlaces_red')->where('vlans_permitidas', 'VLAN 1')->update(['vlans_permitidas' => null]);

        // Tráfico simulado (18500000 / 9200000) escrito como "octetos"
        DB::table('telemetria_interfaces')->whereIn('in_octets', [18500000])->update(['in_octets' => null, 'out_octets' => null]);

        // Dúplex, autonegociación, modo, VLAN y PoE nunca se han recolectado del equipo:
        // los valores guardados son los de por defecto, no mediciones.
        DB::table('interfaces_red')->update(['duplex' => null, 'autoneg' => null, 'mode' => null, 'vlan_id' => null]);
        DB::table('telemetria_interfaces')->update(['poe_status' => null]);

        // Chasis: temperatura simulada "Chasis Principal (Óptimo)", RAM/PoE por defecto, uptime ficticio
        DB::table('telemetria_chasis')->where('sensores_temperatura', 'like', '%Chasis Principal%')
            ->update(['temperatura_c' => null, 'sensores_temperatura' => null]);
        DB::table('telemetria_chasis')->update(['poe_total_budget_w' => null, 'poe_used_w' => null, 'poe_available_w' => null]);
        DB::table('telemetria_chasis')->where(function ($q) {
            $q->where(fn ($a) => $a->where('ram_total_mb', 4096)->where('ram_used_mb', 1638))
              ->orWhere(fn ($a) => $a->where('ram_total_mb', 512)->where('ram_used_mb', 128))
              ->orWhere('ram_total_mb', 0);
        })->update(['ram_total_mb' => null, 'ram_used_mb' => null]);
        DB::table('telemetria_chasis')->whereIn('uptime_str', ['Activo en Red', '14d 6h 22m', '0d 0h 0m'])
            ->update(['uptime_str' => null]);
        DB::table('telemetria_chasis')->whereIn('serial_number', ['FG100FTK21004859', 'FOC2438L8PQ'])
            ->orWhere('serial_number', 'like', 'SN-%')->update(['serial_number' => null]);

        // ---------------- Una fila de estado por equipo / interfaz ----------------
        // tablas_dispositivo: conservar por equipo la fila más reciente que tenga datos
        DB::statement("
            DELETE t FROM tablas_dispositivo t
            JOIN (
                SELECT dispositivo_id,
                       COALESCE(MAX(CASE WHEN JSON_LENGTH(COALESCE(mac_table, '[]')) > 0
                                           OR JSON_LENGTH(COALESCE(arp_table, '[]')) > 0
                                           OR JSON_LENGTH(COALESCE(vlans_table, '[]')) > 0 THEN id END),
                                MAX(id)) AS keep_id
                FROM tablas_dispositivo GROUP BY dispositivo_id
            ) k ON k.dispositivo_id = t.dispositivo_id
            WHERE t.id <> k.keep_id
        ");
        foreach (['telemetria_chasis' => 'dispositivo_id', 'telemetria_interfaces' => 'interfaz_id'] as $table => $col) {
            DB::statement("
                DELETE t FROM {$table} t
                JOIN (SELECT {$col}, MAX(id) AS keep_id FROM {$table} GROUP BY {$col}) k ON k.{$col} = t.{$col}
                WHERE t.id <> k.keep_id
            ");
        }

        // Huérfanos (sin equipo / sin interfaz) antes de restaurar llaves foráneas
        DB::statement('DELETE t FROM telemetria_chasis t LEFT JOIN dispositivos d ON d.id = t.dispositivo_id WHERE d.id IS NULL');
        DB::statement('DELETE t FROM telemetria_interfaces t LEFT JOIN interfaces_red i ON i.id = t.interfaz_id WHERE i.id IS NULL');
        DB::statement('DELETE t FROM tablas_dispositivo t LEFT JOIN dispositivos d ON d.id = t.dispositivo_id WHERE d.id IS NULL');

        $this->ensureUnique('telemetria_chasis', 'dispositivo_id', 'uq_disp_id');
        $this->ensureUnique('telemetria_interfaces', 'interfaz_id', 'uq_interfaz_id');
        $this->ensureUnique('tablas_dispositivo', 'dispositivo_id', 'uq_tablas_disp_id');

        $this->ensureForeign('telemetria_chasis', 'dispositivo_id', 'dispositivos');
        $this->ensureForeign('telemetria_interfaces', 'interfaz_id', 'interfaces_red');
        $this->ensureForeign('tablas_dispositivo', 'dispositivo_id', 'dispositivos');
    }

    public function down(): void
    {
        // Los datos simulados eliminados no se restauran.
        Schema::table('metricas_red', function (Blueprint $table) {
            if (Schema::hasColumn('metricas_red', 'traffic_in_mbps')) {
                $table->dropColumn(['traffic_in_mbps', 'traffic_out_mbps']);
            }
        });
        Schema::table('global_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('global_metrics', 'traffic_in_mbps')) {
                $table->dropColumn(['traffic_in_mbps', 'traffic_out_mbps']);
            }
        });
    }

    private function ensureUnique(string $table, string $column, string $name): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && $index['columns'] === [$column]) {
                return;
            }
        }
        Schema::table($table, fn (Blueprint $t) => $t->unique($column, $name));
    }

    private function ensureForeign(string $table, string $column, string $references): void
    {
        foreach (Schema::getForeignKeys($table) as $fk) {
            if ($fk['columns'] === [$column]) {
                return;
            }
        }
        Schema::table($table, fn (Blueprint $t) => $t->foreign($column)->references('id')->on($references)->cascadeOnDelete());
    }
};
