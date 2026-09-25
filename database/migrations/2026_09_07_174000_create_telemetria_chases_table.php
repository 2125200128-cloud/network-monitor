<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetria_chasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
            
            $table->decimal('temperatura_c', 5, 2)->nullable();
            $table->json('estado_fuentes')->nullable();
            $table->json('estado_ventiladores')->nullable();
            
            $table->decimal('cpu_utilization', 5, 2)->nullable();
            $table->decimal('ram_utilization', 5, 2)->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetria_chasis');
    }
};
