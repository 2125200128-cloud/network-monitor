<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruta de red de cada recaudadora (recaudadoras:rutas, traceroute cada 6 h): los saltos desde el servidor del NOC
 * hasta su FortiGate. Con ella el mapa de Topología dibuja por dónde llega cada sede al core.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recaudadoras', function (Blueprint $table) {
            if (!Schema::hasColumn('recaudadoras', 'ruta')) {
                $table->json('ruta')->nullable();
            }
            if (!Schema::hasColumn('recaudadoras', 'ruta_medida_en')) {
                $table->timestamp('ruta_medida_en')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('recaudadoras', function (Blueprint $table) {
            foreach (['ruta', 'ruta_medida_en'] as $c) {
                if (Schema::hasColumn('recaudadoras', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
