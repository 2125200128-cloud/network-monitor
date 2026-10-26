<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hosts ESXi de la granja de virtualización y su vCenter. Se registran como servidores para que el sondeo
 * los vigile desde ya (ping); el detalle de VMware (VMs, CPU, memoria, datastores) lo da el vCenter.
 */
return new class extends Migration
{
    private const EQUIPOS = [
        '10.4.249.26' => 'x7esx01.shp.jal.mx',
        '10.4.249.27' => 'x7esx02.shp.jal.mx',
        '10.4.249.28' => 'x7esx03.shp.jal.mx',
        '10.4.249.29' => 'x7esx04.shp.jal.mx',
        '10.4.249.30' => 'x7esx05.shp.jal.mx',
        '10.4.249.41' => 'x7esx06.shp.jal.mx',
        '10.4.249.42' => 'x7esx07.shp.jal.mx',
        '10.4.249.43' => 'x7esx08.shp.jal.mx',
        '10.4.249.31' => 'vCenter Server',
    ];

    public function up(): void
    {
        foreach (self::EQUIPOS as $ip => $nombre) {
            if (DB::table('dispositivos')->where('ip', $ip)->exists()) {
                continue;
            }
            DB::table('dispositivos')->insert([
                'nombre' => $nombre,
                'ip' => $ip,
                'ubicacion' => 'Centro de datos · Virtualización',
                'estado' => 'offline',          // el sondeo pone el estado real en su siguiente vuelta
                'tipo_manual' => 'servidor',
                'fabricante' => 'VMware',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dispositivos')->whereIn('ip', array_keys(self::EQUIPOS))->delete();
    }
};
