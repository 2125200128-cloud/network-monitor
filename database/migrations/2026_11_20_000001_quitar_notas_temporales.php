<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Se quita el apartado Notas (portapapeles temporal que se usó para pasar los comandos de la CA a las pantallas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('notas_temporales');
    }

    public function down(): void
    {
        // Sin vuelta atrás: el apartado ya no existe en el código.
    }
};
