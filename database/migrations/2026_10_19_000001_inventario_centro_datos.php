<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario físico del centro de datos (DTIF) para la topología "Centro de datos".
 * Cada fila es un equipo físico; las filas con el mismo `nodo` forman un solo nodo lógico
 * (par HA o stack) que comparte IP de administración. El estado en vivo se toma del monitoreo por IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_centro_datos', function (Blueprint $table) {
            $table->id();
            $table->string('nodo', 40)->index();          // agrupa pares HA y stacks
            $table->string('capa', 20);                   // perimetro | balanceo | core | agregacion | acceso | inalambrico
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('rol', 20)->default('unico');  // unico | primario | secundario | miembro
            $table->string('marca', 40);
            $table->string('modelo', 60);
            $table->string('serie', 40)->nullable();
            $table->string('descripcion', 120)->nullable();
            $table->string('version_os', 40)->nullable();
            $table->string('ip', 45)->nullable()->index();
            $table->string('hostname', 80)->nullable();
            $table->timestamps();
        });

        $filas = [
            // nodo, capa, orden, rol, marca, modelo, serie, descripción, versión, IP, hostname
            ['fortigate', 'perimetro', 1, 'primario', 'Fortinet', 'FortiGate 601E', 'FG6H1ETB21903470', 'Firewall', null, '10.4.1.1', 'FGT-SHP-DTIF-01'],
            ['fortigate', 'perimetro', 1, 'secundario', 'Fortinet', 'FortiGate 601E', 'FG6H1ETB21903480', 'Firewall HA', null, '10.4.1.1', 'FGT-SHP-DTIF-02'],

            ['fortiadc', 'balanceo', 1, 'primario', 'Fortinet', 'Forti ADC 400F', 'FAD4HFTA22000097', 'Balanceador web', null, '10.4.1.5', 'FAD400F-01'],
            ['fortiadc', 'balanceo', 1, 'secundario', 'Fortinet', 'Forti ADC 400F', 'FAD4HFTA22000076', 'Balanceador web HA', null, '10.4.1.5', 'FAD400F-02'],
            ['a10-1', 'balanceo', 2, 'unico', 'A10', 'TH1030S', 'TH10A93314361018', 'Balanceador web', null, '10.4.1.7', null],
            ['a10-2', 'balanceo', 3, 'unico', 'A10', 'TH1030S', 'TH10A93314361073', 'Balanceador web', null, '10.4.1.8', null],

            ['n7k-pri', 'core', 1, 'unico', 'Cisco', 'C1-N7004-S2-R', 'JAE21440DDG', 'Core nexus Primario', '8.2(7a)', '10.4.254.2', 'Nexus7K_Core_Pri'],
            ['n7k-sec', 'core', 2, 'unico', 'Cisco', 'C1-N7004-S2-R', 'JAE21440DDH', 'Core nexus secundario GLBP', '8.2(7a)', '10.4.254.3', 'Nexus7K_Core_Sec'],

            ['nx9k-access-1', 'agregacion', 1, 'unico', 'Cisco', 'C1-N9K-C9372TX-E', 'FDO21421A7P', 'Nexus RJ45', null, '10.4.254.41', 'Nx9K-access-1'],
            ['nx9k-access-2', 'agregacion', 2, 'unico', 'Cisco', 'C1-N9K-C9372TX-E', 'FDO21421A7H', 'Nexus RJ45', null, '10.4.254.42', 'Nx9K-access-2'],
            ['nx9k-server-i', 'agregacion', 3, 'unico', 'Cisco', 'C1-N9K-C9372TX-E', 'FDO21421A6K', 'Switch Conexión Web 172.0.0.0', null, '10.4.254.44', 'Nexus9K_server_i'],
            ['nx9k-server-d', 'agregacion', 4, 'unico', 'Cisco', 'C1-N9K-C9372TX-E', 'FDO21421A6E', 'Switch Conexión Web 172.0.0.0', null, '10.4.254.43', 'Nexus9K_server_d'],
            ['nx9k-web', 'agregacion', 5, 'unico', 'Cisco', 'C1-N9K-C9372TX-E', 'FDO214219J1', 'Switch Conexión Web 172.0.0.0', null, '192.168.43.4', 'Nx9K-web'],
            ['nx3k-fibras', 'agregacion', 6, 'unico', 'Cisco', 'N3K-C3172PQ-10GE', 'FOC2144R0DD', 'Nexus fibra', null, '10.4.254.40', 'Nx3K-fibras'],

            ['users-dtif', 'acceso', 1, 'miembro', 'Cisco', 'C9200L-48P-4X', 'FOC26340JX7', 'SWITCH DE USUARIOS stack', null, '10.4.254.36', 'Users_DTIF'],
            ['users-dtif', 'acceso', 1, 'miembro', 'Cisco', 'C9200L-48P-4X', 'FOC26302X9T', 'SWITCH DE USUARIOS stack', null, '10.4.254.36', 'Users_DTIF'],
            ['users-dtif', 'acceso', 1, 'miembro', 'Cisco', 'C9200L-48P-4X', 'FOC26353SS9', 'SWITCH DE USUARIOS stack', null, '10.4.254.36', 'Users_DTIF'],
            ['users-dtif', 'acceso', 1, 'miembro', 'Cisco', 'C9200L-48P-4X', 'FOC26353R5L', 'SWITCH DE USUARIOS stack', null, '10.4.254.36', 'Users_DTIF'],
            ['users-dtif', 'acceso', 1, 'miembro', 'Cisco', 'C9200L-48P-4X', 'FOC26353SRZ', 'SWITCH DE USUARIOS stack', null, '10.4.254.36', 'Users_DTIF'],
            ['users-dtif', 'acceso', 1, 'miembro', 'Cisco', 'C9200L-24P-4X', 'JAE26190ZHV', 'SWITCH DE USUARIOS stack', null, '10.4.254.36', 'Users_DTIF'],
            ['users-dtif', 'acceso', 1, 'miembro', 'Cisco', 'C9200L-24PXG-4X', 'JAE26340BYC', 'SWITCH DE USUARIOS stack', null, '10.4.254.36', 'Users_DTIF'],
            ['sw-server-dtf', 'acceso', 2, 'unico', 'Cisco', 'WS-C3750-24PS-S V06', 'FDO1240X02F', 'Switch Antenas Microondas', null, '10.4.254.30', 'SW_SERVER_DTF'],
            ['sw-rss', 'acceso', 3, 'unico', 'Cisco', 'WS-C2960X-24PS-L', 'FCW2146B1W8', 'Switch RSS', null, '10.4.254.17', null],
            ['sw-resp4', 'acceso', 4, 'unico', 'Cisco', 'WS-C2960X-48FPS-L', 'FOC1747S3R3', 'SWITCH RED 43', null, '192.168.43.90', 'SW-RESP4'],

            ['wlc-9800', 'inalambrico', 1, 'primario', 'Cisco', 'C9800-L-F-K9', 'FCL264901T2', 'Controladora Wifi', null, '10.4.200.100', 'WLC-9800'],
            ['wlc-9800', 'inalambrico', 1, 'secundario', 'Cisco', 'C9800-L-F-K9', 'FCL260900XS', 'Controladora Wifi HA', null, '10.4.200.100', 'WLC-9800'],
        ];

        $ahora = now();
        DB::table('inventario_centro_datos')->insert(array_map(fn ($f) => [
            'nodo' => $f[0], 'capa' => $f[1], 'orden' => $f[2], 'rol' => $f[3], 'marca' => $f[4], 'modelo' => $f[5],
            'serie' => $f[6], 'descripcion' => $f[7], 'version_os' => $f[8], 'ip' => $f[9], 'hostname' => $f[10],
            'created_at' => $ahora, 'updated_at' => $ahora,
        ], $filas));
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_centro_datos');
    }
};
