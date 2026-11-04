<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La consola ya acepta bloques de configuración de varias líneas (Administrador y Operador): el comando
 * registrado en la auditoría deja de caber en 255 caracteres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditoria_comandos', function (Blueprint $table) {
            $table->text('comando_solicitado')->change();
        });
    }

    public function down(): void
    {
        Schema::table('auditoria_comandos', function (Blueprint $table) {
            $table->string('comando_solicitado', 255)->change();
        });
    }
};
