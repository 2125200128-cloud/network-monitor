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
            'ssh_user' => 'nullable|string|max:255',
            'ssh_password' => 'nullable|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535'
        ]);

        $dispositivo = new Dispositivo();
        $dispositivo->nombre = $validated['nombre'];
        $dispositivo->ip = $validated['ip'];
        $dispositivo->comunidad_snmp = $validated['comunidad_snmp'];
        $dispositivo->ubicacion = $validated['ubicacion'];
        // El estado real lo determina snmp_poller.py en su siguiente ciclo (≈15 s).
        $dispositivo->estado = 'offline';
        $dispositivo->ssh_user = $validated['ssh_user'] ?? 'admin';
        $dispositivo->ssh_password_encrypted = !empty($validated['ssh_password'])
            ? Crypt::encryptString($validated['ssh_password'])
            : null;
        $dispositivo->ssh_port = $validated['ssh_port'] ?? 22;
        $dispositivo->save();

        // Chasis, métricas e interfaces se llenan con lo que reporte el equipo
        // (snmp_poller.py cada 15 s y snmp_detail_collector.py cada 5 min).
        return redirect()->route('dashboard')->with('success', 'Dispositivo "' . $dispositivo->nombre . '" registrado. Su estado y telemetría aparecerán tras el primer sondeo SNMP/ICMP.');
    }

    /**
     * Corrección manual del tipo (prevalece sobre la clasificación por evidencia).
     * Enviar tipo vacío/null vuelve a usar la clasificación automática.
     */
    public function corregirTipo(Request $request, int $id)
    {
        $data = $request->validate([
            'tipo' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Services\TipoDispositivoResolver::tipos())],
        ]);
        $dispositivo = Dispositivo::findOrFail($id);
        $dispositivo->update(['tipo_manual' => ($data['tipo'] ?? null) ?: null]);
        \App\Http\Controllers\TopologiaController::olvidarCacheGrafo();

        return response()->json(['success' => true, 'tipo' => $dispositivo->fresh()->resolveTypeInfo()]);
    }
}
