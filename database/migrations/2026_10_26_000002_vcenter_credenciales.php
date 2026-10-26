<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Conexión de solo lectura al vCenter (sección Virtualización). La contraseña se guarda cifrada con APP_KEY. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_generales', 'vcenter_host')) {
                $table->string('vcenter_host', 255)->nullable()->default('10.4.249.31');
                $table->string('vcenter_usuario', 128)->nullable();
                $table->text('vcenter_password')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            $table->dropColumn(['vcenter_host', 'vcenter_usuario', 'vcenter_password']);
        });
    }
};
