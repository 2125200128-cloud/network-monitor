<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comunidad SNMP propia de los firewalls (FortiGate). Si está definida, los equipos de tipo
 * "firewall" la usan en lugar de su comunidad individual; vacía = sin cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_generales', 'comunidad_snmp_firewall')) {
                $table->string('comunidad_snmp_firewall')->nullable()->after('comunidad_snmp_default');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_generales', 'comunidad_snmp_firewall')) {
                $table->dropColumn('comunidad_snmp_firewall');
            }
        });
    }
};
