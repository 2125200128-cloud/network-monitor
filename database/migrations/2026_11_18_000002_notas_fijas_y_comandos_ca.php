<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notas fijas (sin caducidad, expira_en vacío): comandos de uso frecuente que no deben borrarse solos. Solo un Admin
 * las crea o borra. Se deja fija la de los comandos para instalar la CA del NOC en las PCs Ubuntu de las pantallas.
 */
return new class extends Migration
{
    private const TITULO = 'CA del NOC en las pantallas Ubuntu (candado seguro)';

    public function up(): void
    {
        Schema::table('notas_temporales', fn (Blueprint $table) => $table->timestamp('expira_en')->nullable()->change());

        if (DB::table('notas_temporales')->where('titulo', self::TITULO)->exists()) {
            return;
        }
        DB::table('notas_temporales')->insert([
            'user_id' => null,
            'titulo' => self::TITULO,
            'privada' => false,
            'expira_en' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'contenido' => <<<'TXT'
# 1. Descargar la CA del NOC (solo la parte pública) y ver su huella
wget --no-check-certificate -qO /tmp/noc-ca.crt https://10.4.150.194/noc-ca.crt
openssl x509 -in /tmp/noc-ca.crt -noout -subject -fingerprint -sha256

# 2. Confianza del sistema
sudo cp /tmp/noc-ca.crt /usr/local/share/ca-certificates/noc-network-monitor-ca.crt
sudo update-ca-certificates

# 3. Chrome / Chromium
sudo apt install -y libnss3-tools
mkdir -p ~/.pki/nssdb
certutil -d sql:$HOME/.pki/nssdb -A -t "C,," -n "NOC Network Monitor CA" -i /tmp/noc-ca.crt

# 3b. Solo si Chromium es de snap
mkdir -p ~/snap/chromium/current/.pki/nssdb && certutil -d sql:$HOME/snap/chromium/current/.pki/nssdb -A -t "C,," -n "NOC Network Monitor CA" -i /tmp/noc-ca.crt

# 4. Firefox (también el de snap)
sudo mkdir -p /etc/firefox/policies
echo '{"policies":{"Certificates":{"ImportEnterpriseRoots":true,"Install":["/usr/local/share/ca-certificates/noc-network-monitor-ca.crt"]}}}' | sudo tee /etc/firefox/policies/policies.json

# 5. Cierra el navegador por completo y vuelve a abrir https://10.4.150.194 (debe salir el candado sin aviso)
# La huella del paso 1 debe coincidir con la del servidor: sudo openssl x509 -in /etc/ssl/network-monitor/ca.crt -noout -fingerprint -sha256
TXT,
        ]);
    }

    public function down(): void
    {
        DB::table('notas_temporales')->where('titulo', self::TITULO)->delete();
        DB::table('notas_temporales')->whereNull('expira_en')->update(['expira_en' => now()->addDay()]);
        Schema::table('notas_temporales', fn (Blueprint $table) => $table->timestamp('expira_en')->nullable(false)->change());
    }
};
