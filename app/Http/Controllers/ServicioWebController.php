<?php

namespace App\Http\Controllers;

use App\Models\ServicioWeb;
use App\Services\WebServicePoller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServicioWebController extends Controller
{
    protected WebServicePoller $poller;

    public function __construct(WebServicePoller $poller)
    {
        $this->poller = $poller;
    }

    public function index(Request $request)
    {
        $query = ServicioWeb::query();

        if ($request->filled('categoria') && $request->categoria !== 'todas') {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        $servicios = $query->orderBy('categoria')->orderBy('nombre')->get();

        $stats = [
            'total' => ServicioWeb::count(),
            'online' => ServicioWeb::where('estado', 'online')->count(),
            'offline' => ServicioWeb::where('estado', 'offline')->count(),
            'warning' => ServicioWeb::where('estado', 'warning')->count(),
            'avg_latency' => round(ServicioWeb::where('estado', '!=', 'offline')->avg('tiempo_respuesta_ms') ?? 0, 1),
        ];

        $categorias = ServicioWeb::select('categoria')->distinct()->pluck('categoria');

        return view('servicios_web.index', compact('servicios', 'stats', 'categorias'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'url' => 'required|url',
            'categoria' => 'required|string|max:50',
            'metodo' => 'nullable|string|in:GET,POST,HEAD',
        ]);

        $servicio = ServicioWeb::create([
            'nombre' => $validated['nombre'],
            'url' => $validated['url'],
            'categoria' => $validated['categoria'],
            'metodo' => $validated['metodo'] ?? 'GET',
            'es_activo' => true,
        ]);

        // Probar inmediatamente la nueva URL
        $this->poller->checkService($servicio);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Servicio web agregado y comprobado correctamente.',
                'servicio' => $servicio
            ]);
        }

        return redirect()->route('servicios_web.index')->with('status', 'Servicio web agregado y comprobado exitosamente.');
    }

    public function update(Request $request, $id)
    {
        $servicio = ServicioWeb::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'url' => 'required|url',
            'categoria' => 'required|string|max:50',
            'metodo' => 'nullable|string|in:GET,POST,HEAD',
            'es_activo' => 'nullable|boolean',
        ]);

        $servicio->update([
            'nombre' => $validated['nombre'],
            'url' => $validated['url'],
            'categoria' => $validated['categoria'],
            'metodo' => $validated['metodo'] ?? 'GET',
            'es_activo' => $request->has('es_activo') ? (bool)$request->es_activo : $servicio->es_activo,
        ]);

        if ($servicio->es_activo) {
            $this->poller->checkService($servicio);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Servicio web actualizado correctamente.',
                'servicio' => $servicio
            ]);
        }

        return redirect()->route('servicios_web.index')->with('status', 'Servicio web actualizado correctamente.');
    }

    public function destroy($id)
    {
        $servicio = ServicioWeb::findOrFail($id);
        $servicio->delete();

        return redirect()->route('servicios_web.index')->with('status', 'Servicio web eliminado.');
    }

    public function reprobar($id): JsonResponse
    {
        $servicio = ServicioWeb::findOrFail($id);
        $resultado = $this->poller->checkService($servicio);

        return response()->json([
            'success' => true,
            'resultado' => $resultado,
            'servicio' => $servicio->fresh()
        ]);
    }

    public function reprobarTodos(): JsonResponse
    {
        $resultados = $this->poller->checkAllServices();

        $stats = [
            'total' => ServicioWeb::count(),
            'online' => ServicioWeb::where('estado', 'online')->count(),
            'offline' => ServicioWeb::where('estado', 'offline')->count(),
            'warning' => ServicioWeb::where('estado', 'warning')->count(),
            'avg_latency' => round(ServicioWeb::where('estado', '!=', 'offline')->avg('tiempo_respuesta_ms') ?? 0, 1),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Se verificaron ' . count($resultados) . ' servicios web.',
            'resultados' => $resultados,
            'stats' => $stats
        ]);
    }

    public function apiLive(): JsonResponse
    {
        $servicios = ServicioWeb::orderBy('categoria')->orderBy('nombre')->get();

        $stats = [
            'total' => ServicioWeb::count(),
            'online' => ServicioWeb::where('estado', 'online')->count(),
            'offline' => ServicioWeb::where('estado', 'offline')->count(),
            'warning' => ServicioWeb::where('estado', 'warning')->count(),
            'avg_latency' => round(ServicioWeb::where('estado', '!=', 'offline')->avg('tiempo_respuesta_ms') ?? 0, 1),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'servicios' => $servicios
        ]);
    }
}
