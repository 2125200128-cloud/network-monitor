<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comunidad SNMP fijada en la ficha técnica del equipo. Tiene la mayor prioridad:
 *   ficha (comunidad_snmp_manual) > firewalls (Configuración) > comunidad registrada (comunidad_snmp).
 * comunidad_snmp sigue siendo la del alta / descubrimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            if (!Schema::hasColumn('dispositivos', 'comunidad_snmp_manual')) {
                $table->string('comunidad_snmp_manual')->nullable()->after('comunidad_snmp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            if (Schema::hasColumn('dispositivos', 'comunidad_snmp_manual')) {
                $table->dropColumn('comunidad_snmp_manual');
            }
        });
    }
};
