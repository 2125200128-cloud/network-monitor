<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetria_interfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interfaz_id')->constrained('interfaces_red')->cascadeOnDelete();
            $table->string('oper_status')->nullable(); // up/down/testing
            
            // 64-bit counters
            $table->unsignedBigInteger('in_octets')->nullable();
            $table->unsignedBigInteger('out_octets')->nullable();
            
            $table->unsignedBigInteger('in_errors')->nullable();
            $table->unsignedBigInteger('out_errors')->nullable();
            $table->unsignedBigInteger('in_discards')->nullable();
            $table->unsignedBigInteger('out_discards')->nullable();
            
            // Optics / PoE (Cisco or Generic)
            $table->decimal('optica_rx_dbm', 8, 2)->nullable();
            $table->decimal('optica_tx_dbm', 8, 2)->nullable();
            $table->decimal('poe_watts_consumo', 8, 2)->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetria_interfaces');
    }
};
