<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EnlaceRed;
use App\Models\Dispositivo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncCdpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cdp:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza vecinos CDP ejecutando test_cdp.py y actualiza la tabla enlaces_red';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando descubrimiento CDP profundo (Deep Discovery)...');
        
        $scriptPath = base_path('test_cdp.py');
        if (!file_exists($scriptPath)) {
            $this->error('No se encontró test_cdp.py en la raíz del proyecto.');
            return;
        }

        $dispositivosBase = Dispositivo::where('ip', '!=', '0.0.0.0')
                                       ->whereNotNull('ip')
                                       ->get();

        $this->info("Se escanearán " . $dispositivosBase->count() . " equipos.");
        $pythonBin = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';

        foreach ($dispositivosBase as $origen) {
            $this->line("Consultando IP: {$origen->ip} ({$origen->nombre})...");
            $output = shell_exec("{$pythonBin} " . escapeshellarg($scriptPath) . " " . escapeshellarg($origen->ip) . " 2>&1");

            if (empty($output)) continue;

            $json = null;
            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                if (str_starts_with($line, '{')) {
                    $decoded = json_decode($line, true);
                    if ($decoded !== null) {
                        $json = $decoded;
                        break;
                    }
                }
            }

            if ($json === null || !isset($json['status']) || $json['status'] !== 'success') {
                continue; // Silenciar y pasar al siguiente si hay timeout o no soporta CDP
            }

            if ($json['total_vecinos'] == 0) {
                continue; // No tiene vecinos, pasar al siguiente
            }

            $this->info("✓ [ÉXITO] {$origen->ip} reportó {$json['total_vecinos']} vecinos CDP.");

            // Si es el Core, forzar el renombrado estético
            $config = \App\Models\ConfiguracionGeneral::first();
            $coreIp = $config ? $config->ip_switch_core : env('IP_SWITCH_CORE', '10.4.254.3');
            
            if ($origen->ip === $coreIp) {
                $origen->update(['nombre' => 'Nexus 7000 Core']);
            }

            foreach ($json['adyacencias'] as $index => $vecinoData) {
                $hostname = $vecinoData['hostname'];
                $ip = $vecinoData['ip'];
                
                $hostnameLimpio = explode('.', $hostname)[0];
                $destino = null;
                
                if (!empty($ip)) {
                    $destino = Dispositivo::where('ip', $ip)->first();
                }
                
                if (!$destino) {
                    $destino = Dispositivo::where('nombre', 'LIKE', "%{$hostnameLimpio}%")
                                          ->orWhere('ip', $hostnameLimpio)
                                          ->first();
                }

                if (!$destino) {
                    $dummyMac = 'CD:P0:' . substr(implode(':', str_split(substr(md5($hostnameLimpio), 0, 8), 2)), 0, 11);
                    $destino = Dispositivo::create([
                        'nombre' => $hostnameLimpio,
                        'ip' => !empty($ip) ? $ip : '0.0.0.0',
                        'estado' => 'online',
                        'comunidad_snmp' => 'public',
                        'mac_address' => $dummyMac,
                        'ubicacion' => 'Descubierto vía CDP'
                    ]);
                    $this->warn("   ! [DUMMY] Se creó dummy para IP: '{$ip}' ({$hostnameLimpio})");
                } else {
                    if (str_contains($destino->nombre, 'Nodo Genérico')) {
                        $destino->update(['nombre' => $hostnameLimpio]);
                        $this->line("   ✓ [RENAME] IP {$destino->ip} renombrada a '{$hostnameLimpio}'");
                    }
                }

                EnlaceRed::updateOrCreate(
                    [
                        'origen_dispositivo_id' => $origen->id,
                        'destino_dispositivo_id' => $destino->id,
                    ],
                    [
                        'switch_origen' => $origen->nombre,
                        'switch_destino' => $destino->nombre,
                        'tipo_medio' => 'cobre_1g',
                        'estado' => 'up',
                        'velocidad_mbps' => 1000,
                        'trafico_in_mbps' => 0,
                        'trafico_out_mbps' => 0,
                    ]
                );
            }
        }

        $this->info('Escaneo profundo de CDP completado con éxito.');
    }
}
