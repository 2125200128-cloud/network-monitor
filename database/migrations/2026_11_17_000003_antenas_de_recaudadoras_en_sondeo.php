<?php

use App\Models\RecaudadoraEquipo;
use Illuminate\Database\Migrations\Migration;

/**
 * Las antenas de las recaudadoras que ya estaban en el sondeo (alta manual como «DTI-…») las clasificó el sondeo como PC.
 * Se les pone el tipo nuevo «antena». Las que se registren después las corrige RecaudadoraEquipo al guardarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (RecaudadoraEquipo::where('tipo', 'antena')->pluck('ip') as $ip) {
            RecaudadoraEquipo::marcarAntenaEnSondeo($ip);
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás automática: el tipo se puede cambiar en la ficha de cada equipo
    }
};
