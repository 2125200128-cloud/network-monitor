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
        Schema::create('enlaces_red', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origen_dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
            $table->foreignId('origen_interfaz_id')->nullable()->constrained('interfaces_red')->nullOnDelete();
            $table->foreignId('destino_dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
            $table->foreignId('destino_interfaz_id')->nullable()->constrained('interfaces_red')->nullOnDelete();
            $table->enum('tipo_medio', ['fibra_10g', 'cobre_1g', 'port_channel', 'trunk'])->default('cobre_1g');
            $table->enum('estado', ['up', 'down', 'degraded'])->default('up');
            $table->unsignedInteger('velocidad_mbps')->default(1000);
            $table->decimal('trafico_in_mbps', 8, 2)->default(0);
            $table->decimal('trafico_out_mbps', 8, 2)->default(0);
            $table->string('vlans_permitidas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enlaces_red');
    }
};
