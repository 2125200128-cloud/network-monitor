<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué servidor atiende cada sitio web:
 * - ip_servidor: la IP a la que se conectó el monitor en la última revisión (cURL primary_ip). Si coincide con
 *   la IP de una VM leída de los ESXi, el sitio se relaciona con esa VM automáticamente.
 * - vms_manual: nombres de VMs elegidos a mano, para sitios detrás de un balanceador o VIP (la IP pública/VIP
 *   no es la de ninguna VM).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios_web', function (Blueprint $table) {
            if (!Schema::hasColumn('servicios_web', 'ip_servidor')) {
                $table->string('ip_servidor', 45)->nullable();
            }
            if (!Schema::hasColumn('servicios_web', 'vms_manual')) {
                $table->json('vms_manual')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('servicios_web', function (Blueprint $table) {
            $table->dropColumn(['ip_servidor', 'vms_manual']);
        });
    }
};
