<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lectura directa de los hosts ESXi (worker/esxi_collector.py, API vim25):
 * - credenciales en configuraciones_generales (contraseña cifrada con APP_KEY),
 * - esxi_estado: última lectura de cada host (CPU, memoria, hardware, sensores, alarmas, VMs y datastores),
 * - esxi_metricas: CPU y memoria por minuto para las gráficas (se conservan 7 días).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_generales', 'esxi_usuario')) {
                $table->string('esxi_usuario', 128)->nullable();
                $table->text('esxi_password')->nullable();
            }
        });

        if (!Schema::hasTable('esxi_estado')) {
            Schema::create('esxi_estado', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dispositivo_id')->unique()->constrained('dispositivos')->cascadeOnDelete();
                $table->string('conexion', 20)->nullable();          // connected / disconnected / notResponding
                $table->boolean('mantenimiento')->nullable();
                $table->string('estado_general', 10)->nullable();    // green / yellow / red / gray (overallStatus)
                $table->float('cpu_pct')->nullable();
                $table->float('ram_pct')->nullable();
                $table->unsignedInteger('cpu_mhz_usado')->nullable();
                $table->unsignedInteger('cpu_mhz_total')->nullable();
                $table->unsignedInteger('ram_mb_usado')->nullable();
                $table->unsignedInteger('ram_mb_total')->nullable();
                $table->unsignedBigInteger('uptime_seg')->nullable();
                $table->string('fabricante', 80)->nullable();
                $table->string('modelo', 120)->nullable();
                $table->string('cpu_modelo', 160)->nullable();
                $table->unsignedSmallInteger('nucleos')->nullable();
                $table->unsignedSmallInteger('hilos')->nullable();
                $table->string('version', 120)->nullable();
                $table->json('sensores')->nullable();
                $table->json('alarmas')->nullable();
                $table->json('vms')->nullable();
                $table->json('datastores')->nullable();
                $table->text('error')->nullable();                   // último error de lectura (null si leyó bien)
                $table->timestamp('leido_en')->nullable();           // última lectura correcta
                $table->timestamp('intentado_en')->nullable();       // último intento (bien o mal)
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('esxi_metricas')) {
            Schema::create('esxi_metricas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
                $table->float('cpu_pct')->nullable();
                $table->float('ram_pct')->nullable();
                $table->unsignedSmallInteger('vms_encendidas')->nullable();
                $table->timestamp('fecha');
                $table->index(['dispositivo_id', 'fecha']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('esxi_metricas');
        Schema::dropIfExists('esxi_estado');
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            $table->dropColumn(['esxi_usuario', 'esxi_password']);
        });
    }
};
