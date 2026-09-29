<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ip')->unique();
            $table->string('mac_address', 17)->nullable()->unique();
            $table->string('comunidad_snmp')->default('public');
            $table->string('ubicacion');
            $table->enum('estado', ['online', 'offline', 'warning'])->default('offline');
            $table->integer('cpu_usage')->nullable();
            $table->integer('memoria_usage')->nullable();
            $table->timestamp('ultimo_monitoreo')->nullable();
            $table->timestamp('ultima_vez_visto')->nullable();
            $table->string('ssh_user')->default('admin')->nullable();
            $table->text('ssh_password_encrypted')->nullable();
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos');
    }
};
