<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cámaras de seguridad encontradas en las tablas ARP de los equipos de red (camaras:buscar). Solo inventario:
 * no se monitorean ni generan alarmas. Una cámara se identifica por su MAC (la IP puede cambiar).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('camaras_detectadas')) {
            return;
        }
        Schema::create('camaras_detectadas', function (Blueprint $table) {
            $table->id();
            $table->string('mac', 17)->unique();
            $table->string('ip', 45);
            $table->string('fabricante');
            $table->string('visto_en')->nullable();       // equipo de red en cuya tabla ARP apareció
            $table->string('visto_en_ip', 45)->nullable();
            $table->boolean('rtsp')->nullable();           // puerto de video 554
            $table->boolean('http')->nullable();
            $table->boolean('https')->nullable();
            $table->timestamp('primera_vez')->useCurrent();
            $table->timestamp('ultima_vez')->useCurrent();
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camaras_detectadas');
    }
};
