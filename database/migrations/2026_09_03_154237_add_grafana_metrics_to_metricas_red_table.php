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
        Schema::table('metricas_red', function (Blueprint $table) {
            $table->tinyInteger('cpu_usage')->default(0)->after('ping_ms');
            $table->tinyInteger('memory_usage')->default(0)->after('cpu_usage');
            $table->decimal('packet_loss', 5, 2)->default(0)->after('memory_usage');
            $table->bigInteger('uptime')->default(0)->after('packet_loss');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metricas_red', function (Blueprint $table) {
            $table->dropColumn(['cpu_usage', 'memory_usage', 'packet_loss', 'uptime']);
        });
    }
};
