<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de equipo basado en evidencia (worker/device_classifier.py) y corrección manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->string('tipo_detectado', 20)->nullable()->after('estado');
            $table->string('tipo_confianza', 10)->nullable()->after('tipo_detectado');   // alta | media | baja | ninguna
            $table->string('tipo_fuente', 10)->nullable()->after('tipo_confianza');     // snmp | vecinos | nombre | ninguna
            $table->json('tipo_evidencia')->nullable()->after('tipo_fuente');
            $table->string('tipo_manual', 20)->nullable()->after('tipo_evidencia');     // corrección de un operador
            $table->string('fabricante', 80)->nullable()->after('tipo_manual');
            $table->string('modelo_detectado', 120)->nullable()->after('fabricante');
            $table->string('sys_object_id', 120)->nullable()->after('modelo_detectado');
            $table->unsignedSmallInteger('sys_services')->nullable()->after('sys_object_id');
            $table->timestamp('clasificado_en')->nullable()->after('sys_services');
        });
    }

    public function down(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropColumn(['tipo_detectado', 'tipo_confianza', 'tipo_fuente', 'tipo_evidencia', 'tipo_manual',
                'fabricante', 'modelo_detectado', 'sys_object_id', 'sys_services', 'clasificado_en']);
        });
    }
};
