<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Recaudadoras: las oficinas recaudadoras (y otras oficinas de Hacienda) con sus enlaces, los equipos de cada
 * una (antenas, kioscos, switches…) y sus caídas. Se llenan con el Excel de Enlaces de Telecomunicaciones
 * (recaudadoras:importar o el botón Importar del módulo) y se revisan cada minuto (recaudadoras:revisar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recaudadoras', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();              // número de recaudadora (8, 002A, AFISCAL) u OF-… para oficinas
            $table->string('numero', 20)->nullable();
            $table->string('tipo', 12)->default('recaudadora'); // recaudadora | oficina
            $table->string('nombre', 150);
            $table->string('coordinadora', 30)->nullable();      // 1-8 o Metropolitano
            $table->string('municipio_cve', 3)->nullable();      // clave INEGI (para el mapa)
            $table->string('municipio', 80)->nullable();
            // Enlaces (columnas del Excel)
            $table->string('referencia', 80)->nullable();        // referencia Telmex/Uninet
            $table->string('tipo_enlace', 40)->nullable();       // MPLS | VPN | ENLACE PROPIO FO
            $table->string('uninet', 120)->nullable();           // velocidad/servicio Uninet
            $table->string('id_sitio_red_jalisco', 60)->nullable();
            $table->string('velocidad_red_jalisco', 60)->nullable();
            $table->string('bestel', 255)->nullable();
            $table->string('bestel_referencia', 80)->nullable();
            $table->string('global_king', 60)->nullable();
            $table->string('enlace_backup', 80)->nullable();
            $table->string('gobierno_estado', 60)->nullable();
            $table->string('hemac', 60)->nullable();
            $table->string('numero_referencia', 120)->nullable();
            $table->string('ip_administrativa', 45)->nullable();
            $table->string('ip_loopback', 45)->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            // Estado (recaudadoras:revisar)
            $table->string('estado', 12)->nullable();            // online | lan | offline (null = aún sin revisar)
            $table->timestamp('estado_desde')->nullable();
            $table->timestamp('ultimo_chequeo')->nullable();
            $table->float('latencia_ms')->nullable();
            $table->unsignedSmallInteger('fallos_seguidos')->default(0);
            $table->boolean('admin_ok')->nullable();
            $table->boolean('loopback_ok')->nullable();
            // ¿Ha respondido alguna vez? Una IP que nunca contesta (ping bloqueado) no define el estado
            $table->boolean('admin_alguna_vez')->default(false);
            $table->boolean('loopback_alguna_vez')->default(false);
            $table->timestamps();
            $table->index(['tipo', 'estado']);
        });

        Schema::create('recaudadora_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recaudadora_id')->constrained('recaudadoras')->cascadeOnDelete();
            $table->string('tipo', 20);                          // antena | kiosco | switch | ap | pc | impresora | modem | otro
            $table->string('nombre', 120);
            $table->string('ip', 45);
            $table->string('notas')->nullable();
            $table->string('estado', 12)->nullable();            // online | offline
            $table->timestamp('estado_desde')->nullable();
            $table->timestamp('ultimo_chequeo')->nullable();
            $table->float('latencia_ms')->nullable();
            $table->unsignedSmallInteger('fallos_seguidos')->default(0);
            $table->timestamps();
            $table->unique(['recaudadora_id', 'ip']);
        });

        Schema::create('recaudadora_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recaudadora_id')->constrained('recaudadoras')->cascadeOnDelete();
            $table->foreignId('equipo_id')->nullable()->constrained('recaudadora_equipos')->cascadeOnDelete();
            $table->string('tipo', 20);                          // sin_comunicacion | lan | equipo
            $table->timestamp('inicio');
            $table->timestamp('fin')->nullable();
            $table->index(['recaudadora_id', 'inicio']);
            $table->index('fin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recaudadora_eventos');
        Schema::dropIfExists('recaudadora_equipos');
        Schema::dropIfExists('recaudadoras');
    }
};
