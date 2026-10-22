<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se retira la autenticación de dos factores: el acceso es solo con correo y contraseña.
 * Las columnas estaban vacías (ningún usuario la había activado).
 */
return new class extends Migration
{
    private const COLUMNAS = ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];

    public function up(): void
    {
        $existentes = array_values(array_filter(self::COLUMNAS, fn ($c) => Schema::hasColumn('users', $c)));
        if ($existentes) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($existentes));
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
            }
        });
    }
};
