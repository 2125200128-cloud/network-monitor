<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Red prioritaria (por omisión 10.4.0.0/16, la de Hacienda): sus fallas se marcan en Alertas, en el dashboard y en
 * Telegram, y mientras sigan activas se recuerdan por Telegram cada telegram_recordatorio_min minutos (0 = no).
 * alarmas_red guarda la IP de la alarma (también las que no son de un equipo del sondeo, como las recaudadoras)
 * y cuándo se recordó por última vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_generales', 'redes_prioritarias')) {
                $table->string('redes_prioritarias', 255)->nullable()->default('10.4.0.0/16');
            }
            if (!Schema::hasColumn('configuraciones_generales', 'telegram_recordatorio_min')) {
                $table->unsignedSmallInteger('telegram_recordatorio_min')->default(30);
            }
        });
        Schema::table('alarmas_red', function (Blueprint $table) {
            if (!Schema::hasColumn('alarmas_red', 'ip')) {
                $table->string('ip', 45)->nullable();
            }
            if (!Schema::hasColumn('alarmas_red', 'recordada_telegram_at')) {
                $table->timestamp('recordada_telegram_at')->nullable();
            }
            if (!Schema::hasColumn('alarmas_red', 'recordatorios')) {
                $table->unsignedSmallInteger('recordatorios')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('alarmas_red', function (Blueprint $table) {
            foreach (['ip', 'recordada_telegram_at', 'recordatorios'] as $c) {
                if (Schema::hasColumn('alarmas_red', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            foreach (['redes_prioritarias', 'telegram_recordatorio_min'] as $c) {
                if (Schema::hasColumn('configuraciones_generales', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
