<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aparatos vistos en la red 10.4 (tablas ARP, red:vigilar cada 30 min), uno por MAC. Lo que no se había visto nunca
 * queda "nuevo" hasta que alguien lo revisa (conocido o sospechoso). La primera lectura es la línea base.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('equipos_vistos')) {
            return;
        }
        Schema::create('equipos_vistos', function (Blueprint $table) {
            $table->id();
            $table->string('mac', 17)->unique();
            $table->string('ip', 45);
            $table->string('fabricante')->nullable();
            $table->boolean('mac_aleatoria')->default(false);   // MAC inventada por celulares/laptops: no se avisa
            $table->string('visto_en')->nullable();             // equipo de red en cuya tabla ARP apareció
            $table->string('visto_en_ip', 45)->nullable();
            $table->string('estado', 12)->default('nuevo');      // base | nuevo | conocido | sospechoso
            $table->string('nota')->nullable();
            $table->string('revisado_por')->nullable();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamp('avisado_en')->nullable();          // resumen de Telegram en que se incluyó
            $table->timestamp('primera_vez')->useCurrent();
            $table->timestamp('ultima_vez')->useCurrent();
            $table->index(['estado', 'mac_aleatoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos_vistos');
    }
};
