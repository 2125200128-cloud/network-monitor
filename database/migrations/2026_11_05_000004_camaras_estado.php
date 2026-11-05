<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de cada cámara (camaras:revisar, cada minuto): en línea o caída, desde cuándo y cuánto tarda en responder.
 * Solo se muestra en la página Cámaras: no genera alarmas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camaras_detectadas', function (Blueprint $table) {
            if (!Schema::hasColumn('camaras_detectadas', 'estado')) {
                $table->string('estado', 10)->nullable();             // online | offline (null = aún sin revisar)
                $table->timestamp('estado_desde')->nullable();        // cuándo cambió al estado actual
                $table->timestamp('ultimo_chequeo')->nullable();
                $table->float('latencia_ms')->nullable();
                $table->unsignedSmallInteger('fallos_seguidos')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('camaras_detectadas', function (Blueprint $table) {
            foreach (['estado', 'estado_desde', 'ultimo_chequeo', 'latencia_ms', 'fallos_seguidos'] as $col) {
                if (Schema::hasColumn('camaras_detectadas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
