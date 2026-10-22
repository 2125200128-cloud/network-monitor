<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciales SNMPv3 (usuario/contraseñas) para los equipos de tipo firewall (FortiGate).
 * Las contraseñas se guardan cifradas con APP_KEY (cast "encrypted"); los workers las descifran con la misma clave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            if (!Schema::hasColumn('configuraciones_generales', 'snmpv3_usuario')) {
                $table->string('snmpv3_usuario', 64)->nullable();
                $table->string('snmpv3_nivel', 12)->default('authPriv');      // authPriv | authNoPriv
                $table->string('snmpv3_auth_proto', 8)->default('SHA');       // MD5 | SHA | SHA256 | SHA512
                $table->text('snmpv3_auth_pass')->nullable();
                $table->string('snmpv3_priv_proto', 8)->default('AES');       // DES | AES | AES256
                $table->text('snmpv3_priv_pass')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_generales', function (Blueprint $table) {
            $table->dropColumn(['snmpv3_usuario', 'snmpv3_nivel', 'snmpv3_auth_proto', 'snmpv3_auth_pass', 'snmpv3_priv_proto', 'snmpv3_priv_pass']);
        });
    }
};
