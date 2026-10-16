<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Días de retención de los históricos, editables desde Configuración.
 * NULL = usar el valor por defecto de config/noc.php.
 */
return new class extends Migration
{
    private array $columnas = [
        'retencion_metricas_dias', 'retencion_tendencias_dias', 'retencion_consolidado_dias',
        'retencion_alarmas_dias', 'retencion_servicios_web_dias',
    ];

    public function up(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            foreach ($this->columnas as $col) {
                if (!Schema::hasColumn('configuraciones_generales', $col)) {
                    $table->unsignedSmallInteger($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter($this->columnas,
                fn ($c) => Schema::hasColumn('configuraciones_generales', $c))));
        });
    }
};
