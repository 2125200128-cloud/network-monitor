<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Equipos de recaudadora: ¿ha respondido alguna vez? Un kiosco o terminal que nunca ha contestado (IP equivocada,
 * apagado o con el ping bloqueado) no se muestra como caído ni manda avisos: se muestra «Nunca ha respondido».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('recaudadora_equipos', 'alguna_vez')) {
            Schema::table('recaudadora_equipos', fn (Blueprint $table) => $table->boolean('alguna_vez')->default(false)->after('fallos_seguidos'));
        }
        // Los que hoy están en línea ya respondieron alguna vez
        DB::table('recaudadora_equipos')->where('estado', 'online')->update(['alguna_vez' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('recaudadora_equipos', 'alguna_vez')) {
            Schema::table('recaudadora_equipos', fn (Blueprint $table) => $table->dropColumn('alguna_vez'));
        }
    }
};
