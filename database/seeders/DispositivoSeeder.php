<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Dispositivo;

class DispositivoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dispositivos = [
            [
                'nombre' => 'Cisco SG200-26 Smart Switch',
                'ip' => '192.168.1.254',
                'comunidad_snmp' => 'public',
                'ubicacion' => 'Laboratorio Físico - Mesa de Trabajo',
                'estado' => 'online',
                'ssh_port' => 22,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        Dispositivo::firstOrCreate(['ip' => '192.168.1.254'], $dispositivos[0]);
    }
}
