<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La telemetría debe reflejar únicamente lo que reportan los equipos.
 *
 *  - Las métricas desconocidas pasan a NULL en lugar de valores por defecto inventados
 *    (CPU 0, RAM 4096 MB, presupuesto PoE 740 W, etc.).
 *  - Se agregan columnas de tráfico real (Mbps de entrada/salida).
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
