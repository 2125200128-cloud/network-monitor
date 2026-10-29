<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datastores que el colector de ESXi no guarda (un nombre por línea), p. ej. un volumen retirado que los hosts
 * siguen listando como inaccesible. Se editan en Ajustes → ESXi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_generales', 'esxi_datastores_ignorados')) {
                $table->text('esxi_datastores_ignorados')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_generales', 'esxi_datastores_ignorados')) {
                $table->dropColumn('esxi_datastores_ignorados');
            }
        });
    }
};
