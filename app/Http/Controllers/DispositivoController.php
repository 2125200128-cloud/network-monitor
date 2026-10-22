<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use Illuminate\Http\Request;

/**
 * Acciones sobre equipos ya existentes. El alta manual se eliminó: los equipos se incorporan
 * por descubrimiento (CDP/LLDP/SNMP) desde la topología.
 */
class DispositivoController extends Controller
{
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

    /**
     * Fija (o quita, si llega vacía) la comunidad SNMP en la ficha del equipo. Tiene prioridad sobre la
     * de firewalls y la general. El valor nunca se devuelve ni se registra en el log.
     */
    public function guardarSnmp(Request $request, int $id)
    {
        $data = $request->validate(['comunidad' => 'nullable|string|max:255']);
        $dispositivo = Dispositivo::findOrFail($id);
        $comunidad = trim((string) ($data['comunidad'] ?? '')) ?: null;
        $dispositivo->update(['comunidad_snmp_manual' => $comunidad]);

        \Illuminate\Support\Facades\Log::info('Comunidad SNMP de la ficha ' . ($comunidad ? 'fijada' : 'eliminada'), [
            'dispositivo' => $dispositivo->id . ' ' . $dispositivo->nombre,
            'usuario' => auth()->user()?->email,
        ]);

        return response()->json(['success' => true, 'origen' => $dispositivo->fresh()->comunidadConOrigen()[1]]);
    }

    /**
     * Prueba SNMP v2c contra el equipo (sysName, sysDescr). Usa la comunidad escrita en el formulario
     * o, si viene vacía, la que el sondeo usa actualmente. No guarda nada.
     */
    public function probarSnmp(Request $request, int $id)
    {
        $data = $request->validate(['comunidad' => 'nullable|string|max:255']);
        $dispositivo = Dispositivo::findOrFail($id);
        $comunidad = trim((string) ($data['comunidad'] ?? '')) ?: $dispositivo->comunidadEfectiva();

        $proceso = new \Symfony\Component\Process\Process([\App\Support\Python::binario(), base_path('worker/snmp_check.py'), $dispositivo->ip, $comunidad],
            base_path(), \App\Support\Python::entorno());
        $proceso->setTimeout(20);
        $proceso->run();
        $res = json_decode(trim($proceso->getOutput()), true)[$dispositivo->ip] ?? null;

        if (($res['status'] ?? null) === 'ok' && !empty($res['sysDescr'])) {
            return response()->json([
                'ok' => true,
                'sysName' => $res['sysName'] ?? null,
                'sysDescr' => \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $res['sysDescr']), 160),
            ]);
        }

        return response()->json([
            'ok' => false,
            'mensaje' => 'El equipo no respondió SNMP con esa comunidad. Verifica la comunidad, que la IP del servidor NOC esté '
                . 'autorizada en el equipo y que UDP 161 esté permitido (en FortiGate: hosts de la comunidad y allowaccess snmp).',
        ]);
    }
}
