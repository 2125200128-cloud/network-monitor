<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pérdida de paquetes ICMP (%) de cada revisión de las recaudadoras y su resumen por hora (promedio y máximo). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recaudadora_mediciones', function (Blueprint $table) {
            $table->float('admin_perdida')->nullable();      // % de paquetes perdidos en esa revisión; null: sin IP o sin dato
            $table->float('loopback_perdida')->nullable();
        });
        Schema::table('recaudadora_mediciones_hora', function (Blueprint $table) {
            $table->float('admin_perdida_avg')->nullable();
            $table->float('admin_perdida_max')->nullable();
            $table->float('loopback_perdida_avg')->nullable();
            $table->float('loopback_perdida_max')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recaudadora_mediciones_hora', function (Blueprint $table) {
            $table->dropColumn(['admin_perdida_avg', 'admin_perdida_max', 'loopback_perdida_avg', 'loopback_perdida_max']);
        });
        Schema::table('recaudadora_mediciones', function (Blueprint $table) {
            $table->dropColumn(['admin_perdida', 'loopback_perdida']);
        });
    }
};
