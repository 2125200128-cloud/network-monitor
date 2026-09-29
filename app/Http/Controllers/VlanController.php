<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dispositivo;
use App\Models\AuditoriaComando;
use App\Http\Requests\VlanProvisionRequest;
use App\Http\Requests\VlanPortRequest;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Process\Process;

class VlanController extends Controller
{
    public function index()
    {
        $dispositivos = Dispositivo::where('estado', '!=', 'offline')->get();
        return view('vlans.index', compact('dispositivos'));
    }

    public function provisionVlan(VlanProvisionRequest $request)
    {
        $dispositivo = Dispositivo::findOrFail($request->dispositivo_id);
        
        $commands = [
            'conf t',
            "vlan {$request->vlan_id}",
            "name {$request->vlan_name}"
        ];

        if ($request->filled('ip_address') && $request->filled('subnet_mask')) {
            $commands[] = "interface vlan {$request->vlan_id}";
            $commands[] = "ip address {$request->ip_address} {$request->subnet_mask}";
            $commands[] = 'no shut';
        }

        $commands[] = 'end';
        $commands[] = 'write memory';
        
        $jsonCommands = json_encode($commands);
        
        return $this->executeCommands($dispositivo, $jsonCommands, 'Provision VLAN ' . $request->vlan_id);
    }

    public function assignPort(VlanPortRequest $request)
    {
        $dispositivo = Dispositivo::findOrFail($request->dispositivo_id);
        
        $commands = [
            'conf t',
            "interface {$request->interface_name}",
            "switchport mode {$request->mode}"
        ];

        if ($request->mode === 'access') {
            $commands[] = "switchport access vlan {$request->vlan_id}";
        }

        $commands[] = 'end';
        $commands[] = 'write memory';
        
        $jsonCommands = json_encode($commands);
        
        return $this->executeCommands($dispositivo, $jsonCommands, "Assign Port {$request->interface_name} mode {$request->mode}");
    }
    
    private function executeCommands(Dispositivo $dispositivo, string $jsonCommands, string $humanReadableCommand)
    {
        $sshUser = $dispositivo->ssh_user ?: 'admin';
        $sshPassword = $dispositivo->ssh_password_encrypted ? Crypt::decryptString($dispositivo->ssh_password_encrypted) : ($dispositivo->comunidad_snmp ?: 'admin');
        $sshPort = $dispositivo->ssh_port ?: 22;

        $isAdminRole = ($dispositivo->comunidad_snmp === 'admin' || str_contains(strtolower($dispositivo->comunidad_snmp), 'admin'));

        if (empty($sshPassword) && !$isAdminRole) {
            $this->registrarAuditoria($dispositivo->id, $humanReadableCommand, request()->ip(), 'fallo_conexion', 'No hay contraseña configurada.');
            return response()->json(['error' => 'Error de autenticación SSH.'], 500);
        }

        $workerPath = base_path('worker/vlan_provisioner.py');
        $process = new Process([
            'py', 
            $workerPath, 
            $dispositivo->ip, 
            $sshPort, 
            $sshUser, 
            $sshPassword, 
            $jsonCommands
        ]);
        
        // Wait up to 30 seconds for full interactive setup
        $process->setTimeout(30);

        try {
            $process->run();
            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();
            
            if (!$process->isSuccessful() && empty($output)) {
                $salidaTerminal = !empty($errorOutput) ? $errorOutput : 'Error de conexión o timeout.';
                $this->registrarAuditoria($dispositivo->id, $humanReadableCommand, request()->ip(), 'fallo_conexion', $salidaTerminal);
                return response()->json(['error' => $salidaTerminal], 500);
            }

            $salidaFinal = $output . (!empty($errorOutput) ? "\n" . $errorOutput : '');
            
            $this->registrarAuditoria($dispositivo->id, $humanReadableCommand, request()->ip(), 'autorizado', $salidaFinal);
            return response()->json(['output' => $salidaFinal]);
            
        } catch (\Exception $e) {
            $this->registrarAuditoria($dispositivo->id, $humanReadableCommand, request()->ip(), 'fallo_conexion', $e->getMessage());
            return response()->json(['error' => 'Excepción en la ejecución del worker SSH.'], 500);
        }
    }
    
    private function registrarAuditoria($dispositivoId, $comando, $ipOrigen, $estado, $salida)
    {
        AuditoriaComando::create([
            'user_id' => auth()->id() ?? null,
            'dispositivo_id' => $dispositivoId,
            'comando_solicitado' => $comando,
            'ip_origen' => $ipOrigen,
            'estado_ejecucion' => $estado,
            'salida_terminal' => $salida,
        ]);
    }
}
