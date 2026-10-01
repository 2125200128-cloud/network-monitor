<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Dispositivo;
use App\Models\TablasDispositivo;

class SyncL2DataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'l2:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza tablas L2 (ARP, MAC, VLAN) desde los switches a la base de datos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronización de datos L2 y ARP...');
        
        // Obtener todos los dispositivos. Excluiremos los que contengan 'PC' o 'Computadora'
        $dispositivos = Dispositivo::all();

        if ($dispositivos->isEmpty()) {
            $this->warn('No se encontraron dispositivos en la base de datos.');
            return;
        }

        $scriptPath = base_path('worker/get_switch_l2_data.py');
        $pythonExecutable = 'python';

        foreach ($dispositivos as $dispositivo) {
            $this->info("Consultando L2/ARP para {$dispositivo->nombre} ({$dispositivo->ip})...");
            
            $comunidad = $dispositivo->comunidad_snmp ?: env('SNMP_COMMUNITY', 'public');
            
            $command = escapeshellcmd("$pythonExecutable $scriptPath {$dispositivo->ip} $comunidad");
            $output = shell_exec($command);
            
            if (!$output) {
                $this->error("No se obtuvo respuesta del script para {$dispositivo->ip}.");
                continue;
            }

            $data = json_decode(trim($output), true);
            
            if (!$data || !isset($data['status']) || $data['status'] !== 'success') {
                $this->error("Error procesando {$dispositivo->ip}: " . ($data['message'] ?? 'JSON Invalido'));
                continue;
            }

            // Actualizar o crear registro en tablas_dispositivo
            $tabla = TablasDispositivo::firstOrNew(['dispositivo_id' => $dispositivo->id]);
            $tabla->arp_table = $data['arp_table'] ?? [];
            $tabla->mac_table = $data['mac_table'] ?? [];
            $tabla->vlans_table = $data['vlans_table'] ?? [];
            $tabla->spanning_tree = $data['spanning_tree'] ?? [];
            $tabla->lacp_port_channels = $data['lacp_port_channels'] ?? [];
            $tabla->security_qos_multicast = $data['security_qos_multicast'] ?? [
                'errdisabled_ports' => [],
                'port_security' => [],
                'qos_queues' => [],
                'igmp_snooping' => []
            ];
            $tabla->save();

            // Actualizar telemetría de chasis si hay datos adicionales de hardware
            $chasis = \App\Models\TelemetriaChasis::firstOrNew(['dispositivo_id' => $dispositivo->id]);
            if (!empty($data['serial_number'])) {
                $chasis->serial_number = $data['serial_number'];
            }
            if (!empty($data['os_version'])) {
                $chasis->os_version = $data['os_version'];
            }
            if (!empty($data['ram_total_mb'])) {
                $chasis->ram_total_mb = $data['ram_total_mb'];
                $chasis->ram_used_mb = $data['ram_used_mb'];
                if ($data['ram_total_mb'] > 0) {
                    $chasis->ram_utilization = round(($data['ram_used_mb'] / $data['ram_total_mb']) * 100, 1);
                }
            }
            $chasis->save();

            $this->info("✓ Tablas L2 y Chasis actualizadas para {$dispositivo->nombre} (ARP: " . count($tabla->arp_table) . ", MAC: " . count($tabla->mac_table) . ", VLANs: " . count($tabla->vlans_table) . ")");
        }
        
        $this->info('Sincronización L2 finalizada.');
    }
}
