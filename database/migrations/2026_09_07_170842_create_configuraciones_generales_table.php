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
        Schema::create('configuraciones_generales', function (Blueprint $table) {
            $table->id();
            $table->integer('intervalo_sondeo_segundos')->default(60);
            $table->string('comunidad_snmp_default')->default('public');
            $table->integer('umbral_cpu_warning')->default(85);
            $table->integer('umbral_loss_warning')->default(5);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuraciones_generales');
    }
};
