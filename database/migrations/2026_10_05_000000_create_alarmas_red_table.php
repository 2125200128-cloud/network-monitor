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
        Schema::create('alarmas_red', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispositivo_id')->nullable()->constrained('dispositivos')->onDelete('cascade');
            $table->foreignId('interfaz_id')->nullable()->constrained('interfaces_red')->onDelete('set null');
            $table->foreignId('enlace_id')->nullable()->constrained('enlaces_red')->onDelete('set null');
            
            $table->string('codigo_evento')->index(); // DISP_OFFLINE, LINK_DOWN, ERR_DISABLED, SNMP_TIMEOUT, CPU_OVERLOAD, TEMP_CRITICAL, CRC_ERRORS, etc.
            $table->enum('severidad', ['critica', 'advertencia', 'informativa'])->default('advertencia')->index();
            $table->string('categoria')->default('dispositivo'); // dispositivo, interfaz, hardware, rendimiento, enlace, seguridad
            $table->string('titulo');
            $table->text('mensaje');
            $table->text('causa_raiz')->nullable(); // Explicación inteligente de por qué falló
            $table->string('impacto')->nullable(); // Ej. "Afecta enlace hacia IDF-2 Piso 2"
            $table->text('accion_sugerida')->nullable(); // Recomendación paso a paso
            
            $table->enum('estado', ['activa', 'reconocida', 'resuelta'])->default('activa')->index();
            $table->timestamp('fecha_inicio')->useCurrent();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->string('reconocido_por')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarmas_red');
    }
};
