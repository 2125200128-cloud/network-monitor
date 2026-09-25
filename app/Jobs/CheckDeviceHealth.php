<?php

namespace App\Jobs;

use App\Models\Dispositivo;
use App\Models\ConfiguracionGeneral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;

class CheckDeviceHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dispositivo;

    public function __construct(Dispositivo $dispositivo)
    {
        $this->dispositivo = $dispositivo;
    }

    public function handle(): void
    {
        $config = ConfiguracionGeneral::first();
        $comunidadGlobal = $config ? $config->comunidad_snmp_default : env('SNMP_COMMUNITY', 'public');
        $umbralCpu = $config ? $config->umbral_cpu_warning : 85;

        $pythonScript = base_path('worker/check_health.py');
        $pythonExe = env('PYTHON_PATH', 'python');
        $comunidad = $this->dispositivo->comunidad_snmp ?: $comunidadGlobal;

        $process = new Process([$pythonExe, $pythonScript, $this->dispositivo->ip, $comunidad]);
        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful()) {
            $output = $process->getOutput();
            $data = json_decode($output, true);

            if (is_array($data)) {
                $status = $data['status'] ?? 'Unknown';
                
                // Aplicar lógica de warning para CPU si está online
                if ($status === 'online' && isset($data['cpu']) && $data['cpu'] >= $umbralCpu) {
                    $status = 'warning';
                }

                $this->dispositivo->estado = $status;
                $this->dispositivo->ultima_vez_visto = now();
                $this->dispositivo->save();
                
                \App\Models\MetricaRed::create([
                    'dispositivo_id' => $this->dispositivo->id,
                    'cpu_usage' => $data['cpu'] ?? 0,
                    'memory_usage' => $data['memory'] ?? 0,
                    'ping_ms' => ($status === 'online') ? 1.2 : 0, // Fallback ping since python doesn't measure it yet
                    'fecha_registro' => now()
                ]);
            }
        }
    }
}