<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas temporales (página Notas): portapapeles del NOC para pasar comandos o texto de una PC a otra (p. ej. a las
 * pantallas Ubuntu) sin teclearlos. Cada nota caduca (expira_en) y se borra sola. Privada = solo la ve quien la creó.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notas_temporales')) {
            return;
        }
        Schema::create('notas_temporales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo', 120)->nullable();
            $table->text('contenido');
            $table->boolean('privada')->default(false);
            $table->timestamp('expira_en')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_temporales');
    }
};
