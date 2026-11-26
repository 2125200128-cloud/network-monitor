<?php

namespace App\Http\Controllers;

use App\Console\Commands\RevisarRecaudadorasCommand;
use App\Models\Recaudadora;
use App\Models\RecaudadoraEquipo;
use App\Models\RecaudadoraEvento;
use App\Services\Recaudadoras\DisponibilidadRecaudadoras;
use App\Services\Recaudadoras\EscanerRedRecaudadora;
use App\Services\Recaudadoras\LoteKioscos;
use App\Services\Recaudadoras\ImportadorRecaudadoras;
use App\Services\Recaudadoras\MunicipiosJalisco;
use App\Services\PdfReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Módulo Recaudadoras: tablero con mapa de Jalisco, ficha de cada recaudadora (enlaces, equipos, caídas y
 * disponibilidad), importación del Excel de Enlaces y alta de equipos. El estado lo deja recaudadoras:revisar.
 */
class RecaudadorasController extends Controller
{
    public function index()
    {
        $recas = self::conConteos(Recaudadora::query())->orderBy('tipo')->get();
        $disp24 = DisponibilidadRecaudadoras::porRecaudadora(now()->subDay(), now());
        $disp30 = DisponibilidadRecaudadoras::porRecaudadora(now()->subDays(30), now());

        $lista = $recas->map(fn (Recaudadora $r) => self::fila($r) + [
            'disp24' => $disp24[$r->id] ?? null,
            'disp30' => $disp30[$r->id] ?? null,
        ])->sortBy(fn ($f) => [$f['tipo'] === 'recaudadora' ? 0 : 1, is_numeric(rtrim((string) $f['numero'], 'A..Z')) ? (int) $f['numero'] : 9999, $f['nombre']])->values();

        return view('recaudadoras.index', [
            'lista' => $lista,
            'coordinadoras' => $recas->pluck('coordinadora')->filter()->unique()->sort(SORT_NATURAL)->values(),
            'ultimoChequeo' => $recas->max('ultimo_chequeo'),
            'mesesReporte' => self::mesesReporte(),
            'esAdmin' => self::esAdmin(),
            'puedeEditarEquipos' => in_array(strtolower((string) auth()->user()?->role), ['admin', 'operador'], true),
        ]);
    }

    /** Estado de todas (el tablero lo vuelve a pedir cada 30 s sin recargarse). */
    public function estados()
    {
        return response()->json(self::conConteos(Recaudadora::query())->get()->map(fn ($r) => self::fila($r))->values());
    }

    /** Equipos registrados, kioscos y kioscos que responden (revisión de los últimos 5 min), para el tablero y el mapa. */
    private static function conConteos($q)
    {
        return $q->withCount(['equipos',
            'equipos as kioscos_count' => fn ($e) => $e->where('tipo', 'kiosco'),
            'equipos as kioscos_ok_count' => fn ($e) => $e->where('tipo', 'kiosco')->where('estado', 'online')
                ->where('ultimo_chequeo', '>=', now()->subMinutes(5)),
        ]);
    }

    public function show(Recaudadora $recaudadora)
    {
        $recaudadora->load('equipos');
        $disp = fn ($desde) => DisponibilidadRecaudadoras::porRecaudadora($desde, now(), [$recaudadora->id])[$recaudadora->id] ?? null;

        return view('recaudadoras.show', [
            'r' => $recaudadora,
            'eventos' => $recaudadora->eventos()->with('equipo')->limit(60)->get(),
            'disponibilidad' => ['24 h' => $disp(now()->subDay()), '7 días' => $disp(now()->subDays(7)), '30 días' => $disp(now()->subDays(30))],
            'porDia' => DisponibilidadRecaudadoras::porDia($recaudadora, 30),
            'segmento' => EscanerRedRecaudadora::segmento($recaudadora)['red'] ?? null,
            'esAdmin' => self::esAdmin(),
            'puedeEditarEquipos' => in_array(strtolower((string) auth()->user()?->role), ['admin', 'operador'], true),
        ]);
    }

    /** Da de baja la recaudadora desde su ficha: deja de revisarse, sin alertas ni avisos (se cierran sus caídas abiertas). */
    public function darDeBaja(Request $request, Recaudadora $recaudadora)
    {
        $motivo = $request->validate(['motivo' => 'nullable|string|max:255'])['motivo'] ?? null;
        if ($recaudadora->baja_at) {
            return back()->with('error', 'Esa recaudadora ya estaba dada de baja.');
        }
        $n = \App\Services\Bajas::darDeBajaRecaudadora($recaudadora, $request->user(), $motivo ? trim($motivo) : null);
        return back()->with('success', "{$recaudadora->titulo()} quedó dada de baja: ya no se revisa ni avisa" . ($n ? ($n === 1 ? ' (se cerró 1 alerta abierta).' : " (se cerraron {$n} alertas abiertas).") : '.'));
    }

    public function reactivar(Request $request, Recaudadora $recaudadora)
    {
        \App\Services\Bajas::reactivarRecaudadora($recaudadora, $request->user());
        return back()->with('success', "{$recaudadora->titulo()} vuelve a revisarse cada minuto. La primera revisión solo toma su estado, sin avisar.");
    }

    /** Historial de ping para las gráficas de la ficha (?rango=6h|24h|7d|30d|90d). */
    public function historial(Request $request, Recaudadora $recaudadora)
    {
        $rango = $request->validate(['rango' => 'nullable|in:' . implode(',', array_keys(\App\Services\Recaudadoras\HistorialPing::RANGOS))])['rango'] ?? '24h';
        return response()->json(\App\Services\Recaudadoras\HistorialPing::serie($recaudadora, $rango));
    }

    /** Reporte PDF de disponibilidad de un mes (?mes=2026-11; el mes en curso se cuenta hasta ahora). */
    public function reporte(Request $request, PdfReportService $pdf)
    {
        $mes = $request->validate(['mes' => 'nullable|date_format:Y-m'])['mes'] ?? now()->format('Y-m');
        $desde = Carbon::parse($mes . '-01')->startOfMonth();
        if ($desde->isFuture()) {
            return back()->with('error', 'Ese mes todavía no empieza.');
        }
        $enCurso = $desde->isSameMonth(now());
        $hasta = $enCurso ? now() : $desde->copy()->endOfMonth();

        $recas = Recaudadora::where('activo', true)->get()
            ->sortBy(fn ($r) => [$r->tipo === 'recaudadora' ? 0 : 1, (int) $r->numero, $r->nombre])->values();
        $periodo = mb_convert_case($desde->locale('es')->translatedFormat('F Y'), MB_CASE_TITLE) . ($enCurso ? ' (en curso)' : '');

        return $pdf->descargarReporteRecaudadoras($recas, DisponibilidadRecaudadoras::resumen($desde, $hasta, $recas->pluck('id')->all()),
            DisponibilidadRecaudadoras::caidas($desde, $hasta)->whereIn('recaudadora_id', $recas->pluck('id')), $desde, $hasta, $periodo);
    }

    /** Meses que se pueden pedir en el reporte: desde que se dio de alta la primera recaudadora hasta el actual. */
    private static function mesesReporte(): array
    {
        $primero = Recaudadora::min('created_at');
        $mes = $primero ? Carbon::parse($primero)->startOfMonth() : now()->startOfMonth();
        $out = [];
        while ($mes->lte(now()) && count($out) < 24) {
            $out[$mes->format('Y-m')] = mb_convert_case($mes->locale('es')->translatedFormat('F Y'), MB_CASE_TITLE);
            $mes->addMonth();
        }
        return array_reverse($out, true);
    }

    /** Sube el Excel de Enlaces de Telecomunicaciones y lo importa (se puede repetir: actualiza sin duplicar). */
    public function importar(Request $request, ImportadorRecaudadoras $importador)
    {
        $request->validate(['archivo' => 'required|file|max:10240|mimes:xlsx'],
            ['archivo.mimes' => 'Sube el archivo como Libro de Excel (.xlsx).']);
        try {
            $r = $importador->importar($request->file('archivo')->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo importar: ' . $e->getMessage());
        }
        Log::info('Recaudadoras importadas del Excel', ['usuario' => auth()->user()?->usuario] + array_diff_key($r, ['avisos' => 1, 'sin_municipio' => 1]));

        return back()->with('success', "Importación lista: {$r['creadas']} recaudadoras nuevas, {$r['actualizadas']} completadas con datos que les faltaban, "
            . "{$r['oficinas']} oficinas nuevas y {$r['equipos']} equipos nuevos.")->with('avisosImportacion', $r['avisos']);
    }

    /** Alta de una recaudadora u otra oficina desde el sistema (sin Excel). */
    public function crear()
    {
        return view('recaudadoras.formulario', ['r' => new Recaudadora(['tipo' => 'recaudadora', 'activo' => true]),
            'municipios' => collect(MunicipiosJalisco::todos())->sortBy('nombre')->values()]);
    }

    public function editar(Recaudadora $recaudadora)
    {
        return view('recaudadoras.formulario', ['r' => $recaudadora,
            'municipios' => collect(MunicipiosJalisco::todos())->sortBy('nombre')->values()]);
    }

    public function guardar(Request $request)
    {
        $r = new Recaudadora();
        $this->llenar($r, $request);
        $r->save();
        Log::info('Recaudadora dada de alta en el sistema', ['recaudadora' => $r->titulo(), 'clave' => $r->clave, 'usuario' => auth()->user()?->usuario]);

        return redirect()->route('recaudadoras.show', $r->id)->with('success', $r->titulo() . ' dada de alta: se revisa cada minuto a partir de ahora.');
    }

    /** Todos los datos se corrigen aquí: la base de datos es la fuente de verdad (el Excel solo fue la carga inicial). */
    public function actualizar(Request $request, Recaudadora $recaudadora)
    {
        $this->llenar($recaudadora, $request);
        $cambios = array_keys($recaudadora->getDirty());
        // Si cambia una IP, lo que se sabía de la anterior ya no aplica: se vuelve a revisar desde cero
        $ipNueva = array_intersect($cambios, ['ip_administrativa', 'ip_loopback']);
        if ($ipNueva) {
            $recaudadora->fill(['estado' => null, 'estado_desde' => null, 'fallos_seguidos' => 0, 'latencia_ms' => null,
                'admin_ok' => null, 'loopback_ok' => null]);
            if ($recaudadora->isDirty('ip_administrativa')) {
                $recaudadora->admin_alguna_vez = false;
            }
            if ($recaudadora->isDirty('ip_loopback')) {
                $recaudadora->loopback_alguna_vez = false;
            }
            RecaudadoraEvento::where('recaudadora_id', $recaudadora->id)->whereNull('equipo_id')->whereNull('fin')->update(['fin' => now()]);
        }
        $recaudadora->save();
        if ($cambios) {
            Log::info('Recaudadora editada', ['recaudadora' => $recaudadora->titulo(), 'campos' => $cambios, 'usuario' => auth()->user()?->usuario]);
        }

        return redirect()->route('recaudadoras.show', $recaudadora->id)->with('success', $cambios
            ? 'Datos de ' . $recaudadora->titulo() . ' guardados' . ($ipNueva ? ': la IP cambió y se vuelve a revisar desde cero.' : '.')
            : 'No hubo cambios.');
    }

    /** Borra la recaudadora con sus equipos e historial. Para una oficina que solo cerró conviene desmarcar «Monitorear». */
    public function borrar(Recaudadora $recaudadora)
    {
        $titulo = $recaudadora->titulo();
        $recaudadora->delete();
        Log::warning('Recaudadora borrada del sistema', ['recaudadora' => $titulo, 'clave' => $recaudadora->clave, 'usuario' => auth()->user()?->usuario]);

        return redirect()->route('recaudadoras.index')->with('success', "{$titulo} se borró del sistema con sus equipos e historial.");
    }

    /** Valida el formulario completo y pasa los datos al modelo (sin guardar). La clave se deriva del número o del nombre. */
    private function llenar(Recaudadora $r, Request $request): void
    {
        $reglas = [
            'tipo' => ['required', Rule::in(array_keys(Recaudadora::TIPOS))],
            'numero' => ['nullable', 'required_if:tipo,recaudadora', 'regex:/^\d{1,4}[A-Za-z]?$/'],
            'nombre' => 'required|string|max:150',
            'coordinadora' => ['nullable', 'regex:/^([1-9]\d?|Metropolitano)$/'],
            'municipio_cve' => ['nullable', Rule::in(collect(MunicipiosJalisco::todos())->pluck('cve'))],
            'ip_administrativa' => 'nullable|ipv4',
            'ip_loopback' => 'nullable|ipv4',
            'notas' => 'nullable|string|max:2000',
            'activo' => 'boolean',
        ];
        foreach (Recaudadora::CAMPOS_ENLACE as $campo => [, $largo]) {
            $reglas[$campo] = "nullable|string|max:{$largo}";
        }
        $datos = $request->validate($reglas, [
            'numero.required_if' => 'Una recaudadora necesita su número.',
            'numero.regex' => 'El número es de hasta 4 dígitos y, si acaso, una letra (por ejemplo 002A).',
            'ip_administrativa.ipv4' => 'La IP administrativa no es una IPv4 válida.',
            'ip_loopback.ipv4' => 'La IP loopback no es una IPv4 válida.',
        ]);

        $esReca = $datos['tipo'] === 'recaudadora';
        // Si no cambia de tipo ni de número conserva su clave (p. ej. «R-19-2», la segunda 19 que venía repetida en el Excel)
        $mismaIdentidad = $r->exists && $r->tipo === $datos['tipo'] && (!$esReca || strtoupper((string) $r->numero) === strtoupper($datos['numero']));
        $clave = match (true) {
            $mismaIdentidad => $r->clave,
            $esReca => Recaudadora::claveDeNumero($datos['numero']),
            default => 'OF-' . Str::limit(Str::slug($datos['nombre']), 36, ''),
        };
        if (Recaudadora::where('clave', $clave)->when($r->exists, fn ($q) => $q->where('id', '!=', $r->id))->exists()) {
            throw ValidationException::withMessages([$esReca ? 'numero' : 'nombre' => $esReca
                ? 'Ya existe una recaudadora con ese número.' : 'Ya existe una oficina con ese nombre.']);
        }

        $mun = MunicipiosJalisco::porClave($datos['municipio_cve'] ?? null)
            ?? ($r->exists ? null : MunicipiosJalisco::resolver($datos['nombre'], $datos['coordinadora'] ?? null));
        $vacio = fn ($v) => ($v = trim((string) $v)) === '' ? null : $v;

        $r->fill([
            'clave' => $clave,
            'tipo' => $datos['tipo'],
            'numero' => $esReca ? strtoupper($datos['numero']) : $vacio($datos['numero'] ?? null),
            'nombre' => trim($datos['nombre']),
            'coordinadora' => $datos['coordinadora'] ?? null,
            'municipio_cve' => $mun['cve'] ?? null,
            'municipio' => $mun['nombre'] ?? null,
            'ip_administrativa' => $datos['ip_administrativa'] ?? null,
            'ip_loopback' => $datos['ip_loopback'] ?? null,
            'notas' => $vacio($datos['notas'] ?? null),
            'activo' => $request->boolean('activo'),
        ] + collect(Recaudadora::CAMPOS_ENLACE)->map(fn ($x, $campo) => $vacio($datos[$campo] ?? null))->all());
    }

    public function guardarEquipo(Request $request, Recaudadora $recaudadora)
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(RecaudadoraEquipo::TIPOS))],
            'nombre' => 'required|string|max:120',
            'ip' => ['required', 'ipv4', Rule::unique('recaudadora_equipos', 'ip')->where('recaudadora_id', $recaudadora->id)],
            'notas' => 'nullable|string|max:250',
        ], ['ip.unique' => 'Esa IP ya está registrada en esta recaudadora.', 'ip.ipv4' => 'No es una IPv4 válida.']);
        $recaudadora->equipos()->create($datos);
        Log::info('Equipo de recaudadora agregado', ['recaudadora' => $recaudadora->titulo(), 'ip' => $datos['ip'], 'usuario' => auth()->user()?->usuario]);

        return back()->with('success', "{$datos['nombre']} agregado: se revisa cada minuto a partir de ahora.");
    }

    /** Alta en bloque de kioscos y sus terminales de pago pegando la lista («Reca 000 1 10.4.6.9 terminal 10.4.6.61»). */
    public function loteKioscos(Request $request, LoteKioscos $lote)
    {
        $texto = $request->validate(['lista' => 'required|string|max:20000'], ['lista.required' => 'Pega la lista de kioscos.'])['lista'];
        $res = $lote->guardar($lote->interpretar($texto));
        Log::info('Kioscos de recaudadoras agregados en bloque', ['nuevos' => $res['nuevos'], 'existentes' => $res['existentes'],
            'avisos' => count($res['avisos']), 'usuario' => auth()->user()?->usuario]);

        return back()
            ->with($res['nuevos'] ? 'success' : 'error', $res['nuevos']
                ? "{$res['nuevos']} equipos agregados (kioscos y terminales de pago): se revisan cada minuto a partir de ahora."
                : 'No se agregó ningún equipo.')
            ->with('avisosImportacion', $res['avisos'])
            ->with('avisosTitulo', 'líneas de la lista que revisar');
    }

    /** Busca qué equipos contestan en la red interna de la recaudadora (JSON para la ficha; tarda hasta ~20 s). */
    public function escanear(Recaudadora $recaudadora, EscanerRedRecaudadora $escaner)
    {
        set_time_limit(180);
        try {
            $res = $escaner->escanear($recaudadora);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        Log::info('Búsqueda de equipos en la red de una recaudadora', ['recaudadora' => $recaudadora->titulo(), 'red' => $res['red'],
            'encontrados' => count($res['equipos']), 'usuario' => auth()->user()?->usuario]);
        return response()->json($res);
    }

    public function borrarEquipo(Recaudadora $recaudadora, RecaudadoraEquipo $equipo)
    {
        abort_unless($equipo->recaudadora_id === $recaudadora->id, 404);
        $equipo->delete();
        return back()->with('success', "{$equipo->nombre} quitado de la recaudadora.");
    }

    /** Lo que el tablero necesita de cada recaudadora (también lo usa el refresco de estados). */
    public static function fila(Recaudadora $r): array
    {
        $estado = $r->estadoVisible();
        return [
            'id' => $r->id,
            'tipo' => $r->tipo,
            'numero' => $r->numero,
            'nombre' => $r->nombre,
            'titulo' => $r->titulo(),
            'coordinadora' => $r->coordinadora,
            'municipio' => $r->municipio,
            'cve' => $r->municipio_cve,
            'enlace' => trim(implode(' ', array_filter([$r->tipo_enlace, $r->uninet]))),
            'ip' => $r->ip_administrativa,
            'loopback' => $r->ip_loopback,
            'porLoopback' => $estado === 'online' && !$r->admin_ok && $r->loopback_ok,
            'estado' => $estado,
            'ms' => $r->latencia_ms !== null ? round($r->latencia_ms, 1) : null,
            'desde' => $r->estado_desde?->format($r->estado_desde->isToday() ? 'H:i' : 'd/m H:i'),
            'hace' => $r->estado_desde && in_array($estado, ['offline', 'lan'], true)
                ? RevisarRecaudadorasCommand::duracion((int) $r->estado_desde->diffInSeconds(now(), true)) : null,
            'equipos' => $r->equipos_count ?? 0,
            'kioscos' => $r->kioscos_count ?? 0,
            'kioscos_ok' => $r->kioscos_ok_count ?? 0,
            'activo' => $r->activo,
        ];
    }

    private static function esAdmin(): bool
    {
        return strtolower((string) auth()->user()?->role) === 'admin';
    }
}
