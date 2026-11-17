<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * El acceso pasa de correo y contraseña a usuario y contraseña.
 * A cada cuenta existente se le asigna como usuario lo que va antes de la @ de su correo
 * (admin@hacienda.gob.mx → admin), en minúsculas; si dos coinciden, la segunda lleva un número (admin2).
 * El correo se conserva, pero ya no es obligatorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'usuario')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('usuario', 50)->nullable()->after('name'));
        }

        $usados = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email', 'usuario']) as $u) {
            $base = $u->usuario ?: self::normalizar(Str::before((string) $u->email, '@'));
            $base = $base !== '' ? $base : 'usuario' . $u->id;
            $usuario = $base;
            for ($n = 2; in_array($usuario, $usados, true); $n++) {
                $usuario = $base . $n;
            }
            $usados[] = $usuario;
            DB::table('users')->where('id', $u->id)->update(['usuario' => $usuario]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('usuario', 50)->nullable(false)->change();
            $table->unique('usuario');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['usuario']);
            $table->dropColumn('usuario');
        });
    }

    /** Solo letras, números, punto, guion y guion bajo, en minúsculas (lo mismo que acepta el formulario de cuentas). */
    private static function normalizar(string $texto): string
    {
        return substr(preg_replace('/[^a-z0-9._-]/', '', Str::lower(Str::ascii($texto))), 0, 50);
    }
};
