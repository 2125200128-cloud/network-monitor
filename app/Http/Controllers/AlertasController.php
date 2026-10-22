<?php

namespace App\Http\Controllers;

use App\Services\AlarmDiagnosisService;
use App\Services\HistorialAlertas;
use App\Services\PdfReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Sección Alertas: incidencias activas (diagnóstico en vivo) e historial (alarmas_red). */
class AlertasController extends Controller
{
    public function index(Request $request, HistorialAlertas $historial)
    {
        $pestana = $request->query('pestana') === 'historial' ? 'historial' : 'activas';
        $datos = ['pestana' => $pestana, 'categorias' => HistorialAlertas::CATEGORIAS];

        if ($pestana === 'activas') {
            $datos['activas'] = $this->activas();
        } else {
            $f = $historial->filtros($request);
            $datos += ['filtros' => $f, 'filas' => $historial->filas($f), 'resumen' => $historial->resumen($f)];
        }
        return view('alertas.index', $datos);
    }

    /** Incidencias activas de toda la red (sin ocultar las descartadas: esta vista es la lista completa). */
    private function activas(): array
    {
        $alarmas = AlarmDiagnosisService::diagnosticoCompartido();

        return collect($alarmas)
            ->reject(fn ($a) => ($a['severidad'] ?? '') === 'informativa')
            ->map(function ($a) {
                try {
                    $inicio = Carbon::createFromFormat('d/m/Y H:i:s', (string) ($a['fecha_falla'] ?? ''));
                } catch (\Throwable) {
                    $inicio = null;
                }
                return [
                    'id' => $a['id'],
                    'severidad' => $a['severidad'] ?? $a['tipo'] ?? 'advertencia',
                    'categoria' => $a['categoria'] ?? 'dispositivo',
                    'codigo' => $a['codigo_evento'] ?? '',
                    'titulo' => $a['titulo'] ?? '',
                    'mensaje' => $a['mensaje'] ?? '',
                    'causa' => $a['causa_raiz'] ?? '',
                    'accion' => $a['accion_sugerida'] ?? '',
                    'impacto' => $a['impacto'] ?? '',
                    'equipo' => $a['dispositivo'] ?? '',
                    'ip' => $a['ip'] ?? '',
                    'ubicacion' => $a['ubicacion'] ?? '',
                    'dispositivo_id' => $a['dispositivo_id'] ?? null,
                    'link' => $a['link'] ?? null,
                    'inicio' => $inicio?->format('d/m/Y H:i'),
                    'inicio_ts' => $inicio?->timestamp ?? 0,
                    'duracion' => $inicio ? HistorialAlertas::duracion($inicio->diffInSeconds(now())) : '—',
                    'cascada' => ($a['codigo_evento'] ?? '') === 'CASCADE_FAILURE',
                ];
            })
            ->values()->all();
    }

    /** Historial filtrado en CSV (Excel) o PDF institucional. */
    public function exportar(Request $request, HistorialAlertas $historial, PdfReportService $pdf)
    {
        $f = $historial->filtros($request);
        $filas = $historial->todasLasFilas($f);
        $rango = $f['desde']->format('Ymd') . '-' . $f['hasta']->format('Ymd');

        if ($request->query('formato') === 'pdf') {
            return $pdf->descargarReporteAlertas($f, $historial->resumen($f), $filas);
        }

        $agrupada = $f['vista'] === 'alarma';
        $encabezado = $agrupada
            ? ['Alarma', 'Severidad', 'Categoria', 'Equipo', 'IP', 'Ocurrencias', 'Primera', 'Ultima', 'Tiempo acumulado', 'Abiertas']
            : ['Alarma', 'Severidad', 'Categoria', 'Equipo', 'IP', 'Inicio', 'Resolucion', 'Duracion', 'Estado', 'Causa probable'];
        $cat = HistorialAlertas::CATEGORIAS;

        return response()->streamDownload(function () use ($filas, $encabezado, $agrupada, $cat) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM: Excel abre los acentos correctamente
            fputcsv($out, $encabezado);
            foreach ($filas as $r) {
                fputcsv($out, $agrupada
                    ? [$r->titulo, $r->severidad, $cat[$r->categoria] ?? $r->categoria, $r->equipo, $r->ip, $r->ocurrencias,
                        $r->primera, $r->ultima, HistorialAlertas::duracion($r->total_s), $r->abiertas]
                    : [$r->titulo, $r->severidad, $cat[$r->categoria] ?? $r->categoria, $r->equipo, $r->ip, $r->fecha_inicio,
                        $r->fecha_resolucion ?? 'Activa', HistorialAlertas::duracion($r->duracion_s), $r->estado, $r->causa_raiz]);
            }
            fclose($out);
        }, "Historial_alertas_{$rango}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
