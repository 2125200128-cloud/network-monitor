<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WebServicePoller;

class PollWebServicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'web:poll {--id= : ID específico de servicio web a monitorear}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitorea la disponibilidad HTTP/HTTPS y latencia de las páginas web y servicios del sistema';

    /**
     * Execute the console command.
     */
    public function handle(WebServicePoller $poller): int
    {
        $id = $this->option('id');

        if ($id) {
            $servicio = \App\Models\ServicioWeb::find($id);
            if (!$servicio) {
                $this->error("No se encontró el servicio web ID {$id}");
                return 1;
            }
            $this->info("Monitoreando servicio web: {$servicio->nombre} ({$servicio->url})...");
            $res = $poller->checkService($servicio);
            $this->line("Estado: {$res['estado']} | HTTP: {$res['codigo_http']} | Latencia: {$res['tiempo_respuesta_ms']}ms");
            return 0;
        }

        $this->info("Iniciando sondeo de todos los servicios web configurados...");
        $resultados = $poller->checkAllServices();
        $this->info("Sondeo completado para " . count($resultados) . " sitios/servicios.");

        return 0;
    }
}
