<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telemetría propia de FortiGate (FORTINET-FORTIGATE-MIB), escrita por snmp_detail_collector.py.
 * NULL = el equipo no respondió esa tabla de la MIB.
 */
return new class extends Migration
{
    private array $columnas = ['forti_sistema', 'forti_sensores', 'forti_sdwan', 'forti_vpn_ssl', 'forti_seguridad'];

    public function up(): void
    {
        Schema::table('telemetria_chasis', function (Blueprint $table) {
            $despues = 'wifi_clientes';
            foreach ($this->columnas as $col) {
                if (!Schema::hasColumn('telemetria_chasis', $col)) {
                    $table->json($col)->nullable()->after($despues);
                }
                $despues = $col;
            }
        });
    }

    public function down(): void
    {
        Schema::table('telemetria_chasis', function (Blueprint $table) {
            foreach ($this->columnas as $col) {
                if (Schema::hasColumn('telemetria_chasis', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
