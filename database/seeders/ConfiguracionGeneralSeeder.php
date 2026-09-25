<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ConfiguracionGeneral;

class ConfiguracionGeneralSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (ConfiguracionGeneral::count() == 0) {
            ConfiguracionGeneral::create([
                'intervalo_sondeo_segundos' => 60,
                'comunidad_snmp_default' => 'public',
                'umbral_cpu_warning' => 85,
                'umbral_loss_warning' => 5,
            ]);
        }
    }
}
