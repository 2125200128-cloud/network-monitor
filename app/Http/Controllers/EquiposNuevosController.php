<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Equipos → Equipos nuevos: los aparatos de la red 10.4 que nunca se habían visto (red:vigilar). Se revisan uno por
 * uno (conocido o sospechoso, con nota). Revisar: Administrador y Operador; consultar: todos.
 */
class EquiposNuevosController extends Controller
{
    public function index()
    {
        // Para orientar la revisión: si la IP es de un equipo del inventario o la MAC de una cámara ya encontrada
        $inventario = DB::table('dispositivos')->pluck('nombre', 'ip');
        $camaras = DB::table('camaras_detectadas')->get(['mac', 'nombre', 'ip'])->keyBy('mac');
        $fecha = fn ($v) => $v ? Carbon::parse($v)->format('d/m/Y H:i') : null;

        $equipos = DB::table('equipos_vistos')->orderByDesc('primera_vez')->get()->map(fn ($e) => [
            'id' => $e->id, 'ip' => $e->ip, 'mac' => $e->mac, 'fabricante' => $e->fabricante, 'aleatoria' => (bool) $e->mac_aleatoria,
            'visto' => strtok((string) $e->visto_en, '.'), 'estado' => $e->estado, 'nota' => $e->nota,
            'revisado' => $e->revisado_por ? "{$e->revisado_por}, {$fecha($e->revisado_en)}" : null,
            'primera' => $fecha($e->primera_vez), 'ultima' => $fecha($e->ultima_vez),
            'avisado' => (bool) $e->avisado_en,
            'enInventario' => isset($camaras[$e->mac]) ? 'Cámara ' . ($camaras[$e->mac]->nombre ?: $camaras[$e->mac]->ip)
                : ($inventario[$e->ip] ?? null),
        ])->values();

        return view('equipos.nuevos', [
            'equipos' => $equipos,
            'lineaBase' => DB::table('equipos_vistos')->where('estado', 'base')->min('primera_vez'),
            'ultimaLectura' => DB::table('equipos_vistos')->max('ultima_vez'),
            'puedeRevisar' => in_array(strtolower((string) auth()->user()?->role), ['admin', 'operador'], true),
        ]);
    }

    /** Marca un aparato como conocido, sospechoso o de nuevo pendiente, con nota opcional. */
    public function revisar(Request $request, int $id)
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(['conocido', 'sospechoso', 'nuevo'])],
            'nota' => 'nullable|string|max:250',
        ]);
        $n = DB::table('equipos_vistos')->where('id', $id)->update([
            'estado' => $datos['estado'], 'nota' => trim((string) ($datos['nota'] ?? '')) ?: null,
            'revisado_por' => $datos['estado'] === 'nuevo' ? null : auth()->user()?->name,
            'revisado_en' => $datos['estado'] === 'nuevo' ? null : now(),
        ]);
        abort_unless($n, 404);
        return response()->json(['ok' => true]);
    }

    /** Marca como conocidos todos los pendientes de un grupo (de fábrica o de MAC aleatoria). */
    public function aprobarPendientes(Request $request)
    {
        $aleatoria = $request->boolean('aleatoria');
        $n = DB::table('equipos_vistos')->where('estado', 'nuevo')->where('mac_aleatoria', $aleatoria)
            ->update(['estado' => 'conocido', 'revisado_por' => auth()->user()?->name, 'revisado_en' => now()]);
        return response()->json(['ok' => true, 'marcados' => $n]);
    }
}
