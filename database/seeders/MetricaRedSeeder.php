<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MetricaRedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Solo datos reales provenientes del motor SNMP/ICMP físico.
     */
    public function run(): void
    {
        // No generar datos simulados/fantasma.
        // Las métricas se generan en tiempo real a través del snmp_poller.py conectado al switch físico.
    }
}
