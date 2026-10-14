<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rangos de IP por zona (CIDR, comodín o rango) para acomodar los equipos automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zonas_topologia', function (Blueprint $table) {
            $table->string('rangos_ip', 500)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('zonas_topologia', function (Blueprint $table) {
            $table->dropColumn('rangos_ip');
        });
    }
};
