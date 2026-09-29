<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metricas_red', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->onDelete('cascade');
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->float('ping_ms')->nullable();
            $table->tinyInteger('cpu_usage')->default(0);
            $table->tinyInteger('memory_usage')->default(0);
            $table->decimal('packet_loss', 5, 2)->default(0);
            $table->bigInteger('uptime')->default(0);
            $table->tinyInteger('temperatura_celsius')->default(0);
            $table->integer('conexiones_activas')->default(0);
            $table->integer('errores_interfaz')->default(0);
            $table->timestamp('fecha_registro')->useCurrent();
            
            $table->index('fecha_registro');
            $table->index(['dispositivo_id', 'fecha_registro']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metricas_red');
    }
};
