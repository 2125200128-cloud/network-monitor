<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alertas por correo (SMTP), con los mismos dos grupos que Telegram: los destinatarios de redes y los de servidores.
 * La conexión se configura desde Ajustes → Notificaciones (la contraseña se guarda cifrada) y cada alarma recuerda
 * cuándo se avisó y se recordó por correo, aparte de Telegram.
 */
return new class extends Migration
{
    private const CONFIG = ['correo_activo', 'correo_host', 'correo_puerto', 'correo_seguridad', 'correo_verificar_certificado',
        'correo_usuario', 'correo_password', 'correo_remitente', 'correo_remitente_nombre', 'correo_destinos_redes',
        'correo_destinos_servidores', 'correo_severidad_min', 'correo_resueltas', 'correo_recordatorio_min'];

    public function up(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            $nueva = fn (string $c) => !Schema::hasColumn('configuraciones_generales', $c);
            if ($nueva('correo_activo')) {
                $table->boolean('correo_activo')->default(false);
                $table->string('correo_host', 255)->nullable();
                $table->unsignedSmallInteger('correo_puerto')->default(587);
                $table->string('correo_seguridad', 10)->default('tls');          // tls (STARTTLS) · ssl · ninguna
                $table->boolean('correo_verificar_certificado')->default(true);
                $table->string('correo_usuario', 255)->nullable();
                $table->text('correo_password')->nullable();                     // cifrada (cast encrypted)
                $table->string('correo_remitente', 255)->nullable();
                $table->string('correo_remitente_nombre', 120)->nullable();
                $table->text('correo_destinos_redes')->nullable();               // uno por línea
                $table->text('correo_destinos_servidores')->nullable();
                $table->string('correo_severidad_min', 20)->default('critica');
                $table->boolean('correo_resueltas')->default(true);
                $table->unsignedSmallInteger('correo_recordatorio_min')->default(60);
            }
        });
        Schema::table('alarmas_red', function (Blueprint $table) {
            if (!Schema::hasColumn('alarmas_red', 'notificada_correo_at')) {
                $table->timestamp('notificada_correo_at')->nullable();
                $table->timestamp('recordada_correo_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('alarmas_red', function (Blueprint $table) {
            $table->dropColumn(['notificada_correo_at', 'recordada_correo_at']);
        });
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            $table->dropColumn(self::CONFIG);
        });
    }
};
