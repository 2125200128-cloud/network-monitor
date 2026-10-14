<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plano de topología compartido por todo el NOC (posiciones + zonas tipo Packet Tracer)
 * y notas por dispositivo (con contenido cifrado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topologia_plano', function (Blueprint $table) {
            $table->id();
            $table->json('posiciones');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Parte del acomodo personal guardado más recientemente
        $ultimo = DB::table('posiciones_topologia')->orderByDesc('updated_at')->first();
        DB::table('topologia_plano')->insert([
            'posiciones' => $ultimo->posiciones ?? '{}',
            'updated_by' => $ultimo->user_id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('zonas_topologia', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('color', 9)->default('#3b82f6');
            $table->double('x');
            $table->double('y');
            $table->double('ancho');
            $table->double('alto');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notas_dispositivo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispositivo_id')->constrained('dispositivos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo', 120);
            $table->text('contenido'); // cifrado con APP_KEY (cast 'encrypted')
            $table->boolean('es_secreto')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_dispositivo');
        Schema::dropIfExists('zonas_topologia');
        Schema::dropIfExists('topologia_plano');
    }
};
