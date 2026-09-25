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
        Schema::table('enlaces_red', function (Blueprint $table) {
            if (!Schema::hasColumn('enlaces_red', 'switch_origen')) {
                $table->string('switch_origen')->nullable()->after('origen_dispositivo_id');
            }
            if (!Schema::hasColumn('enlaces_red', 'switch_destino')) {
                $table->string('switch_destino')->nullable()->after('destino_dispositivo_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enlaces_red', function (Blueprint $table) {
            if (Schema::hasColumn('enlaces_red', 'switch_origen')) {
                $table->dropColumn('switch_origen');
            }
            if (Schema::hasColumn('enlaces_red', 'switch_destino')) {
                $table->dropColumn('switch_destino');
            }
        });
    }
};
