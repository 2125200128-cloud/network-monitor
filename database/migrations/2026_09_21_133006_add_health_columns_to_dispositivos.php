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
            $table->integer('cpu_usage')->nullable()->after('estado');
            $table->integer('memoria_usage')->nullable()->after('cpu_usage');
            $table->timestamp('ultimo_monitoreo')->nullable()->after('memoria_usage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropColumn(['cpu_usage', 'memoria_usage', 'ultimo_monitoreo']);
        });
    }
};
