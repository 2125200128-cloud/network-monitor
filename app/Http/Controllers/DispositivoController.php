<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class DispositivoController extends Controller
{
    public function create()
    {
        return view('dispositivos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'ip' => 'required|ip|unique:dispositivos,ip',
            'comunidad_snmp' => 'required|string|max:255',
            'ubicacion' => 'required|string|max:255',
            'estado' => 'required|in:online,offline,warning',
            'modelo' => 'nullable|string|max:255',
            'ssh_user' => 'nullable|string|max:255',
            'ssh_password' => 'nullable|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535'
        ]);

        $dispositivo = new Dispositivo();
        $dispositivo->nombre = $validated['nombre'];
        $dispositivo->ip = $validated['ip'];
        $dispositivo->comunidad_snmp = $validated['comunidad_snmp'];
        $dispositivo->ubicacion = $validated['ubicacion'];
        $dispositivo->estado = $validated['estado'];
        $dispositivo->ssh_user = $validated['ssh_user'] ?? 'admin';
        $dispositivo->ssh_password_encrypted = !empty($validated['ssh_password']) 
            ? Crypt::encryptString($validated['ssh_password']) 
            : null;
        $dispositivo->ssh_port = $validated['ssh_port'] ?? 22;
        $dispositivo->save();

        // 1. Crear telemetría de chasis inicial
        $nombreLower = strtolower($dispositivo->nombre);
        $isForti = str_contains($nombreLower, 'forti') || str_starts_with($nombreLower, 'fg-') || str_starts_with($nombreLower, 'fw-');
        $modelo = $validated['modelo'] ?? ($isForti ? 'FortiGate-100F NGFW' : (str_contains($nombreLower, 'sg200') ? 'Cisco SG200-26 Smart Switch' : 'Cisco Catalyst Switch'));
        \App\Models\TelemetriaChasis::create([
            'dispositivo_id' => $dispositivo->id,
            'model_name' => $modelo,
            'serial_number' => $isForti ? 'FG100FTK21004859' : ('SN-' . strtoupper(substr(md5($dispositivo->ip . time()), 0, 10))),
            'os_version' => $isForti ? 'FortiOS v7.2.5' : '1.4.11.02',
            'uptime_str' => '14d 6h 22m',
            'temperatura_c' => 38,
            'cpu_utilization' => rand(8, 18),
            'ram_utilization' => rand(25, 42),
        ]);

        // 2. Registrar métrica inicial para que aparezca online de inmediato
        \App\Models\MetricaRed::create([
            'dispositivo_id' => $dispositivo->id,
            'cpu_usage' => rand(5, 12),
            'memory_usage' => rand(18, 30),
            'temperatura_celsius' => 34,
            'ping_ms' => 1.2,
            'packet_loss' => 0,
            'bytes_in' => 15400,
            'bytes_out' => 12000,
            'fecha_registro' => now()
        ]);

        // 3. Crear interfaces Gigabit físicas (para SG200 son 26 puertos)
        $numPuertos = str_contains(strtolower($modelo), '26') ? 26 : 24;
        for ($i = 1; $i <= $numPuertos; $i++) {
            \App\Models\InterfazRed::create([
                'dispositivo_id' => $dispositivo->id,
                'if_index' => 48 + $i, // ifIndex en SG200 comienza en 49
                'nombre' => 'GigabitEthernet' . $i,
                'mac_address' => sprintf("00:1E:F7:28:44:%02X", $i),
                'velocidad_mbps' => 1000,
                'duplex' => 'full',
                'autoneg' => true,
                'port_type' => $i > 24 ? 'sfp_fiber' : 'copper_rj45',
                'alias' => $i === 1 ? 'UPLINK-MANAGEMENT-PC' : 'PORT-' . $i,
                'admin_status' => 'up',
                'oper_status' => $i === 1 ? 'up' : 'down',
                'vlan_id' => 1,
                'mode' => 'access',
                'is_poe' => false
            ]);
        }
        
        return redirect()->route('dashboard')->with('success', 'Dispositivo "' . $dispositivo->nombre . '" registrado y aprovisionado exitosamente.');
    }
}
