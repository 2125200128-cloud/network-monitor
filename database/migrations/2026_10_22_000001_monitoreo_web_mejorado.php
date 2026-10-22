<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monitoreo web más fino usando solo la URL de cada sitio:
 * - fallos/lentitud consecutivos para confirmar antes de alarmar,
 * - umbral de latencia propio (manual) o automático según su tiempo normal,
 * - texto esperado opcional en la página,
 * - desglose del tiempo de respuesta (DNS, conexión, TLS y servidor),
 * - problema de certificado (dominio o cadena), además de la vigencia que ya se medía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios_web', function (Blueprint $table) {
            if (!Schema::hasColumn('servicios_web', 'fallos_consecutivos')) {
                $table->unsignedSmallInteger('fallos_consecutivos')->default(0);
                $table->unsignedSmallInteger('lentos_consecutivos')->default(0);
                $table->unsignedInteger('latencia_umbral_ms')->nullable();
                $table->float('latencia_base_ms')->nullable();
                $table->string('texto_esperado', 255)->nullable();
                $table->float('tiempo_dns_ms')->nullable();
                $table->float('tiempo_conexion_ms')->nullable();
                $table->float('tiempo_tls_ms')->nullable();
                $table->float('tiempo_servidor_ms')->nullable();
                $table->string('ssl_problema', 255)->nullable();
            }
        });

        Schema::table('historico_servicio_web', function (Blueprint $table) {
            if (!Schema::hasColumn('historico_servicio_web', 'tiempo_dns_ms')) {
                $table->float('tiempo_dns_ms')->nullable();
                $table->float('tiempo_conexion_ms')->nullable();
                $table->float('tiempo_tls_ms')->nullable();
                $table->float('tiempo_servidor_ms')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('historico_servicio_web', function (Blueprint $table) {
            $table->dropColumn(['tiempo_dns_ms', 'tiempo_conexion_ms', 'tiempo_tls_ms', 'tiempo_servidor_ms']);
        });
        Schema::table('servicios_web', function (Blueprint $table) {
            $table->dropColumn(['fallos_consecutivos', 'lentos_consecutivos', 'latencia_umbral_ms', 'latencia_base_ms',
                'texto_esperado', 'tiempo_dns_ms', 'tiempo_conexion_ms', 'tiempo_tls_ms', 'tiempo_servidor_ms', 'ssl_problema']);
        });
    }
};
