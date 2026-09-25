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
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            $table->string('ip_switch_core')->nullable()->after('comunidad_snmp_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            $table->dropColumn('ip_switch_core');
        });
    }
};
