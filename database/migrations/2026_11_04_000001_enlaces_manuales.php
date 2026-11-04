<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces que alguien registró a mano (equipo sin SNMP/CDP/LLDP al darlo de alta). Se distinguen de los
 * descubiertos para poder listarlos y quitarlos sin tocar los que vienen de la red.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlaces_red', function (Blueprint $table) {
            if (!Schema::hasColumn('enlaces_red', 'manual')) {
                $table->boolean('manual')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('enlaces_red', function (Blueprint $table) {
            if (Schema::hasColumn('enlaces_red', 'manual')) {
                $table->dropColumn('manual');
            }
        });
    }
};
