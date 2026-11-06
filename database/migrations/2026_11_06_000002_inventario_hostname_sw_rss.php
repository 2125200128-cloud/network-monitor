<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * sw-rss (10.4.254.17, serie FCW2146B1W8) no tenía hostname; por SNMP reporta sysName SW_RSS_RESPALDO y esa misma serie.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventario_centro_datos')->where('nodo', 'sw-rss')->whereNull('hostname')
            ->update(['hostname' => 'SW_RSS_RESPALDO', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('inventario_centro_datos')->where('nodo', 'sw-rss')->where('hostname', 'SW_RSS_RESPALDO')
            ->update(['hostname' => null, 'updated_at' => now()]);
    }
};
