<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alertas por Telegram: token del bot (cifrado con APP_KEY), chat de destino y qué se envía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_generales', 'telegram_activo')) {
                $table->boolean('telegram_activo')->default(false);
                $table->text('telegram_bot_token')->nullable();
                $table->string('telegram_chat_id', 64)->nullable();
                $table->string('telegram_severidad_min', 20)->default('critica');
                $table->boolean('telegram_resueltas')->default(true);
                $table->boolean('telegram_imagenes')->default(true);
            }
        });

        // Solo se avisa la resolución de alarmas cuyo inicio sí se notificó
        Schema::table('alarmas_red', function (Blueprint $table) {
            if (!Schema::hasColumn('alarmas_red', 'notificada_telegram_at')) {
                $table->timestamp('notificada_telegram_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('alarmas_red', function (Blueprint $table) {
            if (Schema::hasColumn('alarmas_red', 'notificada_telegram_at')) {
                $table->dropColumn('notificada_telegram_at');
            }
        });
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_generales', 'telegram_activo')) {
                $table->dropColumn(['telegram_activo', 'telegram_bot_token', 'telegram_chat_id',
                    'telegram_severidad_min', 'telegram_resueltas', 'telegram_imagenes']);
            }
        });
    }
};
