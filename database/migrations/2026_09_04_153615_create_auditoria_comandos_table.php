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

        Schema::table('dispositivos', function (Blueprint $table) {
            $table->string('ssh_user')->default('admin')->nullable();
            $table->text('ssh_password_encrypted')->nullable();
            $table->unsignedSmallInteger('ssh_port')->default(22);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropColumn(['ssh_user', 'ssh_password_encrypted', 'ssh_port']);
        });
        Schema::dropIfExists('auditoria_comandos');
    }
};
