<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Falla intermitente de un sitio web: un balanceador que sigue mandando visitas a servidores caídos responde bien
 * unas veces y con error otras. Se guardan las últimas peticiones (1 = respondió, 0 = falló, la más reciente al
 * final) y si el sitio está en modo "falla intermitente", para alarmar por el porcentaje de error y no ir y venir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios_web', function (Blueprint $table) {
            if (!Schema::hasColumn('servicios_web', 'muestras')) {
                $table->string('muestras', 40)->nullable();
            }
            if (!Schema::hasColumn('servicios_web', 'falla_parcial')) {
                $table->boolean('falla_parcial')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('servicios_web', function (Blueprint $table) {
            foreach (['muestras', 'falla_parcial'] as $col) {
                if (Schema::hasColumn('servicios_web', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
