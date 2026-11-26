<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de ping de cada recaudadora para las gráficas de su ficha:
 *  - recaudadora_mediciones: cada revisión (una por minuto), 8 días;
 *  - recaudadora_mediciones_hora: resumen por hora (mín/promedio/máx y cuántas respondieron), 400 días.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recaudadora_mediciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recaudadora_id')->constrained('recaudadoras')->cascadeOnDelete();
            $table->timestamp('medido_en');
            $table->boolean('ok');                       // con comunicación, con la misma regla que su estado
            $table->boolean('admin_ok')->nullable();     // null: no tiene IP administrativa
            $table->boolean('loopback_ok')->nullable();
            $table->float('admin_ms')->nullable();       // null: no respondió
            $table->float('loopback_ms')->nullable();
            $table->index(['recaudadora_id', 'medido_en']);
            $table->index('medido_en');
        });

        Schema::create('recaudadora_mediciones_hora', function (Blueprint $table) {
            $table->foreignId('recaudadora_id')->constrained('recaudadoras')->cascadeOnDelete();
            $table->timestamp('hora');
            $table->unsignedSmallInteger('muestras');
            $table->unsignedSmallInteger('ok_muestras');
            $table->float('admin_min')->nullable();
            $table->float('admin_avg')->nullable();
            $table->float('admin_max')->nullable();
            $table->float('loopback_min')->nullable();
            $table->float('loopback_avg')->nullable();
            $table->float('loopback_max')->nullable();
            $table->primary(['recaudadora_id', 'hora']);
            $table->index('hora');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recaudadora_mediciones_hora');
        Schema::dropIfExists('recaudadora_mediciones');
    }
};
