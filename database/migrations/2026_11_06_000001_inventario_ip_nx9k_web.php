<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nx9K-web (serie FDO214219J1) se cargó con 192.168.43.4, IP que no aparece en ninguna tabla ARP. El propio switch
 * anuncia por CDP 172.16.8.1 como su dirección de gestión (lo ven así ambos cores y Nexus9K_server_i).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventario_centro_datos')->where('nodo', 'nx9k-web')->where('ip', '192.168.43.4')
            ->update(['ip' => '172.16.8.1', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('inventario_centro_datos')->where('nodo', 'nx9k-web')->where('ip', '172.16.8.1')
            ->update(['ip' => '192.168.43.4', 'updated_at' => now()]);
    }
};
