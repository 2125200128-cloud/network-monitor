<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Dispositivo;
use App\Jobs\CheckDeviceHealth;
use App\Models\ConfiguracionGeneral;

class HealthCheckCommand extends Command
{
    protected $signature = 'health:check';
    protected $description = 'Dispara los Jobs de monitoreo de estado para los dispositivos en un ciclo continuo';

    public function handle()
    {
        $this->info("Iniciando motor de sondeo en segundo plano (Daemon)...");
        
        while (true) {
            $config = ConfiguracionGeneral::first();
            $intervalo = $config ? $config->intervalo_sondeo_segundos : 60;
            
            $this->info("[" . now()->format('Y-m-d H:i:s') . "] Desencadenando monitoreo para todos los dispositivos...");
            
            $dispositivos = Dispositivo::all();
            
            foreach ($dispositivos as $disp) {
                CheckDeviceHealth::dispatch($disp);
            }
            
            $this->line("Se encolaron {$dispositivos->count()} Jobs de monitoreo. Esperando {$intervalo} segundos...");
            
            // Esperar el intervalo configurado antes de la siguiente iteración
            sleep($intervalo);
        }
    }
}