<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acceso al video de cada cámara (página Cámaras): nombre para reconocerla, usuario y contraseña (cifrada con
 * APP_KEY; nunca se devuelve al navegador) y, si la marca no es estándar, la ruta RTSP de su video.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('camaras_detectadas', function (Blueprint $table) {
            foreach (['nombre' => 'string', 'usuario' => 'string', 'ruta_rtsp' => 'string'] as $col => $tipo) {
                if (!Schema::hasColumn('camaras_detectadas', $col)) {
                    $table->string($col)->nullable();
                }
            }
            if (!Schema::hasColumn('camaras_detectadas', 'password')) {
                $table->text('password')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('camaras_detectadas', function (Blueprint $table) {
            foreach (['nombre', 'usuario', 'ruta_rtsp', 'password'] as $col) {
                if (Schema::hasColumn('camaras_detectadas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
