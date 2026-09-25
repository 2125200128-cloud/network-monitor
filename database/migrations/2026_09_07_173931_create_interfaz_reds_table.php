<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interfaces_red', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
            $table->integer('if_index');
            $table->string('nombre'); // e.g. GigabitEthernet1/0/1
            $table->string('mac_address')->nullable();
            $table->bigInteger('velocidad_mbps')->nullable(); // ifSpeed
            $table->string('admin_status')->nullable(); // up/down
            $table->integer('vlan_id')->nullable();
            $table->boolean('is_poe')->default(false);
            $table->timestamps();

            // if_index must be unique per device
            $table->unique(['dispositivo_id', 'if_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interfaces_red');
    }
};
