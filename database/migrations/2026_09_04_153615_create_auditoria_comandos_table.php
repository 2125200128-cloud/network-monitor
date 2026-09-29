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
        Schema::create('auditoria_comandos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
            $table->string('comando_solicitado');
            $table->string('ip_origen');
            $table->enum('estado_ejecucion', ['autorizado', 'bloqueado', 'fallo_conexion']);
            $table->mediumText('salida_terminal')->nullable();
            $table->timestamp('fecha_ejecucion')->useCurrent();
            
            $table->index(['dispositivo_id', 'fecha_ejecucion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria_comandos');
    }
};
