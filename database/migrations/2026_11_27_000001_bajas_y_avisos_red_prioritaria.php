<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Equipos y recaudadoras «dados de baja» desde su ficha: dejan de generar alertas y avisos (quién, cuándo y por qué).
 * - Avisos por Telegram y correo solo de la red prioritaria (10.4.0.0/16 por omisión); activado desde el inicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['dispositivos', 'recaudadoras'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->timestamp('baja_at')->nullable();
                $table->string('baja_por', 120)->nullable();
                $table->string('baja_motivo', 255)->nullable();
            });
        }
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            $table->boolean('avisar_solo_prioritaria')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_generales', fn (Blueprint $table) => $table->dropColumn('avisar_solo_prioritaria'));
        foreach (['dispositivos', 'recaudadoras'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn(['baja_at', 'baja_por', 'baja_motivo']));
        }
    }
};
