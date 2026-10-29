<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segundo grupo de Telegram: las alarmas de servidores, virtualización y sitios web van a este chat
 * y las de red (switches, firewalls, routers, APs...) al de telegram_chat_id. Vacío = todo al mismo chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_generales', 'telegram_chat_id_servidores')) {
                $table->string('telegram_chat_id_servidores', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones_generales', 'telegram_chat_id_servidores')) {
                $table->dropColumn('telegram_chat_id_servidores');
            }
        });
    }
};
