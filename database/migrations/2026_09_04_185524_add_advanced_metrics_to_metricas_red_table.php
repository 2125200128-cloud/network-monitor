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
        Schema::table('metricas_red', function (Blueprint $table) {
            $table->tinyInteger('temperatura_celsius')->default(0)->after('uptime');
            $table->integer('conexiones_activas')->default(0)->after('temperatura_celsius');
            $table->integer('errores_interfaz')->default(0)->after('conexiones_activas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metricas_red', function (Blueprint $table) {
            $table->dropColumn(['temperatura_celsius', 'conexiones_activas', 'errores_interfaz']);
        });
    }
};
