<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sitios físicos (dependencias, recaudadoras, escuelas…) y el sitio al que pertenece cada equipo.
 * Los de OpenStreetMap se importan con `php artisan noc:importar-sitios`; la asignación es manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sitios_red', function (Blueprint $table) {
            $table->id();
            $table->string('origen', 10)->default('manual');          // osm | manual
            $table->string('origen_ref', 40)->nullable()->unique();   // p. ej. node/123 en OSM
            $table->string('nombre', 200);
            $table->string('categoria', 20)->index();                 // recaudadora | gobierno | ayuntamiento | justicia | seguridad | salud | educacion
            $table->string('direccion')->nullable();
            $table->string('municipio', 80)->nullable()->index();
            $table->string('operador', 150)->nullable();
            $table->double('lat');
            $table->double('lon');
            $table->timestamps();
        });

        Schema::table('dispositivos', function (Blueprint $table) {
            $table->foreignId('sitio_id')->nullable()->after('ubicacion')->constrained('sitios_red')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sitio_id');
        });
        Schema::dropIfExists('sitios_red');
    }
};
