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
        $sinConectividad = WebServicePoller::sinConectividad();

        // Compilar notificaciones para el banner carrusel NOC LIVE
        $notificaciones = (new \App\Http\Controllers\DashboardController)->compilarNotificaciones();
        $unreadNotificaciones = count(array_filter($notificaciones, fn($n) => !$n['leida']));

        return view('servicios_web.index', compact('servicios', 'stats', 'categorias', 'notificaciones', 'unreadNotificaciones', 'sinConectividad'));
    }

    /** Reglas comunes de alta y edición. El umbral vacío = automático; el texto esperado vacío = sin revisar. */
    private function reglas(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'url' => 'required|url',
            'categoria' => 'required|string|max:50',
            'metodo' => 'nullable|string|in:GET,POST,HEAD',
            'latencia_umbral_ms' => 'nullable|integer|min:200|max:7000',
            'texto_esperado' => 'nullable|string|max:255',
        ];
    }

    private function opcionesMonitoreo(array $validated): array
    {
        return [
            'latencia_umbral_ms' => $validated['latencia_umbral_ms'] ?? null,
            'texto_esperado' => trim((string) ($validated['texto_esperado'] ?? '')) ?: null,
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->reglas(), [], [
            'latencia_umbral_ms' => 'umbral de lentitud', 'texto_esperado' => 'texto esperado',
        ]);

        $servicio = ServicioWeb::create([
            'nombre' => $validated['nombre'],
            'url' => $validated['url'],
            'categoria' => $validated['categoria'],
            'metodo' => $validated['metodo'] ?? 'GET',
            'es_activo' => true,
        ] + $this->opcionesMonitoreo($validated));

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

        $validated = $request->validate($this->reglas() + ['es_activo' => 'nullable|boolean'], [], [
            'latencia_umbral_ms' => 'umbral de lentitud', 'texto_esperado' => 'texto esperado',
        ]);

        $servicio->update([
            'nombre' => $validated['nombre'],
            'url' => $validated['url'],
            'categoria' => $validated['categoria'],
            'metodo' => $validated['metodo'] ?? 'GET',
            'es_activo' => $request->has('es_activo') ? (bool)$request->es_activo : $servicio->es_activo,
            // Con otra URL o reglas nuevas, el conteo de fallas empieza de cero
            'fallos_consecutivos' => 0,
            'lentos_consecutivos' => 0,
        ] + $this->opcionesMonitoreo($validated));

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
        $servicios = ServicioWeb::orderBy('categoria')->orderBy('nombre')->get()->map(fn ($s) => self::datosFila($s));

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
            'servicios' => $servicios,
            'sin_conectividad' => WebServicePoller::sinConectividad(),
        ]);
    }

    /** Lo que la tabla necesita de cada sitio (primer render y refresco en vivo usan lo mismo). */
    public static function datosFila(ServicioWeb $s): array
    {
        $r = fn ($v) => $v !== null ? round((float) $v, 1) : null;
        return [
            'id' => $s->id,
            'nombre' => $s->nombre,
            'url' => $s->url,
            'categoria' => $s->categoria,
            'metodo' => $s->metodo ?: 'GET',
            'estado' => $s->estado,
            'codigo_http' => $s->codigo_http,
            'tiempo_respuesta_ms' => $r($s->tiempo_respuesta_ms),
            'ultimo_chequeo_humano' => $s->ultimo_chequeo ? $s->ultimo_chequeo->diffForHumans() : 'Pendiente',
            'detalles_error' => $s->detalles_error,
            'umbral_ms' => $s->umbralLatenciaMs(),
            'umbral_manual' => $s->latencia_umbral_ms,
            'base_ms' => $r($s->latencia_base_ms),
            'texto_esperado' => $s->texto_esperado,
            'tiempos' => [
                'dns' => $r($s->tiempo_dns_ms),
                'conexion' => $r($s->tiempo_conexion_ms),
                'tls' => $r($s->tiempo_tls_ms),
                'servidor' => $r($s->tiempo_servidor_ms),
            ],
            'ssl_dias' => $s->ssl_dias_expiracion,
            'ssl_problema' => $s->ssl_problema,
        ];
    }
}
