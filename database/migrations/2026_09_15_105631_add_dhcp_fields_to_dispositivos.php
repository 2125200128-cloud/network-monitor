<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            // Drop unique index on IP to allow DHCP to shift IPs between devices
            $table->dropUnique('dispositivos_ip_unique');
            
            // Add DHCP identification fields
            $table->string('mac_address', 17)->nullable()->unique()->after('ip');
            $table->timestamp('ultima_vez_visto')->nullable()->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropUnique(['mac_address']);
            $table->dropColumn(['mac_address', 'ultima_vez_visto']);
            $table->unique('ip');
        });
    }
};
