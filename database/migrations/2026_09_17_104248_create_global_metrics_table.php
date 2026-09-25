<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('global_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recorded_at')->useCurrent();

            // Métricas de tráfico y conectividad
            $table->float('traffic_mbps', 8, 2)->default(0);
            $table->float('latency_ms', 8, 2)->default(0);
            $table->float('packet_loss_pct', 5, 2)->default(0);

            // Métricas de cómputo promediadas
            $table->float('cpu_avg_pct', 5, 2)->default(0);
            $table->float('ram_avg_pct', 5, 2)->default(0);

            // Estado general de la red
            $table->unsignedSmallInteger('active_nodes')->default(0);
            $table->float('port_saturation_pct', 5, 2)->default(0);

            // Índice para consultas por rango de tiempo
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_metrics');
    }
};
