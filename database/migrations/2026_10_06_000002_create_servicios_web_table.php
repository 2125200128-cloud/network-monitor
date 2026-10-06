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
        Schema::create('servicios_web', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('url');
            $table->string('metodo', 10)->default('GET');
            $table->string('categoria', 50)->default('General');
            $table->enum('estado', ['online', 'offline', 'warning', 'unknown'])->default('unknown');
            $table->integer('codigo_http')->nullable();
            $table->float('tiempo_respuesta_ms')->nullable();
            $table->boolean('ssl_valido')->nullable();
            $table->integer('ssl_dias_expiracion')->nullable();
            $table->text('detalles_error')->nullable();
            $table->timestamp('ultimo_chequeo')->nullable();
            $table->boolean('es_activo')->default(true);
            $table->timestamps();
        });

        Schema::create('historico_servicio_web', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servicio_web_id')->constrained('servicios_web')->cascadeOnDelete();
            $table->string('estado', 20);
            $table->integer('codigo_http')->nullable();
            $table->float('tiempo_respuesta_ms')->nullable();
            $table->text('detalles_error')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historico_servicio_web');
        Schema::dropIfExists('servicios_web');
    }
};
