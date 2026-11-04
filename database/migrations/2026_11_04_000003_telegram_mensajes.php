<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copia de cada mensaje enviado a Telegram (texto tal cual, si llevaba gráfica, si llegó o el error), para verlos
 * en el NOC sin tener Telegram. La gráfica se guarda en storage/app/telegram/{id}.png. Se conservan 30 días.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('telegram_mensajes')) {
            return;
        }
        Schema::create('telegram_mensajes', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 64);
            $table->text('texto');
            $table->boolean('con_imagen')->default(false);
            $table->boolean('ok')->default(true);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_mensajes');
    }
};
