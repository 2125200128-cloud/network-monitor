<?php

namespace App\Services;

use App\Support\Sql;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Consultas del historial de incidencias (alarmas_red, que llena noc:sync) para la sección Alertas:
 * filtros, vista agrupada por alarma (una fila por falla repetida) o por ocurrencia, indicadores y exportación.
 */
class HistorialAlertas
{
    public const CATEGORIAS = [
        'dispositivo' => 'Equipos', 'enlace' => 'Enlaces', 'hardware' => 'Hardware', 'rendimiento' => 'Rendimiento',
        'seguridad' => 'Seguridad / puertos', 'servicio_web' => 'Servicios web', 'ncm' => 'Respaldos',
        'virtualizacion' => 'Virtualización', 'sistema' => 'Sistema NOC',
    ];

    /** Ocurrencias de la misma alarma en el rango a partir de las cuales se considera intermitente. */
    public const UMBRAL_INTERMITENTE = 5;

    /** Filtros normalizados desde la petición (rango por defecto: últimos 7 días). */
    public function filtros(Request $request): array
    {
        $desde = $this->fecha($request->query('desde')) ?? now()->subDays(7)->startOfDay();
        $hasta = $this->fecha($request->query('hasta')) ?? now();
        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        return [
            'desde' => $desde->copy()->startOfDay(),
            'hasta' => $hasta->copy()->endOfDay(),
            'severidad' => in_array($request->query('severidad'), ['critica', 'advertencia'], true) ? $request->query('severidad') : null,
            'categoria' => array_key_exists((string) $request->query('categoria'), self::CATEGORIAS) ? $request->query('categoria') : null,
            'estado' => in_array($request->query('estado'), ['activa', 'resuelta'], true) ? $request->query('estado') : null,
            'q' => trim(mb_substr((string) $request->query('q'), 0, 80)),
            'vista' => $request->query('vista') === 'ocurrencias' ? 'ocurrencias' : 'alarma',
        ];
    }

    private function fecha($valor): ?Carbon
    {
        try {
            return $valor ? Carbon::createFromFormat('Y-m-d', $valor) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Consulta base con los filtros (alias a = alarmas_red, d = dispositivos). */
    private function base(array $f)
    {
        return DB::table('alarmas_red as a')
            ->leftJoin('dispositivos as d', 'd.id', '=', 'a.dispositivo_id')
            ->whereBetween('a.fecha_inicio', [$f['desde'], $f['hasta']])
            ->when($f['severidad'], fn ($q, $v) => $q->where('a.severidad', $v))
            ->when($f['categoria'], fn ($q, $v) => $q->where('a.categoria', $v))
            ->when($f['estado'] === 'activa', fn ($q) => $q->where('a.estado', '!=', 'resuelta'))
            ->when($f['estado'] === 'resuelta', fn ($q) => $q->where('a.estado', 'resuelta'))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%';
                $q->where(fn ($w) => $w->where('a.titulo', Sql::like(), $like)->orWhere('d.nombre', Sql::like(), $like)->orWhere('d.ip', Sql::like(), $like));
            });
    }

    /** Duración en segundos; las abiertas hasta ahora (hora de la aplicación: las fechas se guardan en hora local). */
    private function duracionSql(): string
    {
        $ahora = now()->format('Y-m-d H:i:s');   // valor propio, no viene del usuario
        $ahoraSql = Sql::esPgsql() ? "TIMESTAMP '{$ahora}'" : "'{$ahora}'";
        return Sql::segundosEntre('a.fecha_inicio', "COALESCE(a.fecha_resolucion, {$ahoraSql})");
    }

    /** Filas paginadas según la vista: una por alarma (agrupada por clave) o una por ocurrencia. */
    public function filas(array $f, int $porPagina = 50)
    {
        $dur = $this->duracionSql();
        if ($f['vista'] === 'ocurrencias') {
            return $this->base($f)
                ->orderByDesc('a.fecha_inicio')
                ->select(['a.id', 'a.clave', 'a.titulo', 'a.severidad', 'a.categoria', 'a.estado', 'a.fecha_inicio', 'a.fecha_resolucion',
                    'a.causa_raiz', 'a.dispositivo_id', 'd.nombre as equipo', 'd.ip'])
                ->selectRaw("{$dur} AS duracion_s")
                ->paginate($porPagina)->withQueryString();
        }
        return $this->base($f)
            ->groupBy('a.clave', 'a.dispositivo_id', 'd.nombre', 'd.ip')
            ->orderByRaw('MAX(a.fecha_inicio) DESC')
            ->select(['a.clave', 'a.dispositivo_id', 'd.nombre as equipo', 'd.ip'])
            ->selectRaw('MAX(a.titulo) AS titulo, MAX(a.categoria) AS categoria, COUNT(*) AS ocurrencias')
            ->selectRaw("MAX(a.severidad) AS severidad")   // 'critica' > 'advertencia': si alguna vez fue crítica, queda crítica
            ->selectRaw('MIN(a.fecha_inicio) AS primera, MAX(a.fecha_inicio) AS ultima')
            ->selectRaw("SUM({$dur}) AS total_s, " . Sql::contarSi("a.estado != 'resuelta'") . ' AS abiertas')
            ->paginate($porPagina)->withQueryString();
    }

    /** Indicadores del rango filtrado. */
    public function resumen(array $f): array
    {
        $dur = $this->duracionSql();
        $t = $this->base($f)->selectRaw('COUNT(*) AS total, COUNT(DISTINCT a.clave) AS alarmas, '
            . Sql::contarSi("a.severidad = 'critica'") . ' AS criticas, '
            . Sql::contarSi("a.estado != 'resuelta'") . ' AS abiertas, '
            . "AVG(CASE WHEN a.estado = 'resuelta' THEN {$dur} END) AS mttr_s")->first();

        $intermitentes = $this->base($f)->groupBy('a.clave')->havingRaw('COUNT(*) >= ?', [self::UMBRAL_INTERMITENTE])->select('a.clave')->get()->count();

        $topEquipos = $this->base($f)->whereNotNull('a.dispositivo_id')
            ->groupBy('a.dispositivo_id', 'd.nombre', 'd.ip')
            ->orderByDesc(DB::raw('COUNT(*)'))->limit(8)
            ->get(['a.dispositivo_id', 'd.nombre', 'd.ip', DB::raw('COUNT(*) AS n'), DB::raw("SUM({$dur}) AS total_s")]);

        $porCategoria = $this->base($f)->groupBy('a.categoria')->get(['a.categoria', DB::raw('COUNT(*) AS n')])
            ->mapWithKeys(fn ($r) => [$r->categoria => (int) $r->n]);

        $porDia = $this->base($f)->groupBy(DB::raw('DATE(a.fecha_inicio)'))->orderBy(DB::raw('DATE(a.fecha_inicio)'))
            ->get([DB::raw('DATE(a.fecha_inicio) AS dia'), DB::raw(Sql::contarSi("a.severidad = 'critica'") . ' AS criticas'), DB::raw('COUNT(*) AS total')])
            ->keyBy(fn ($r) => Carbon::parse($r->dia)->format('Y-m-d'));
        // Todos los días del rango, aunque no tengan incidencias
        $dias = [];
        for ($d = $f['desde']->copy(); $d->lte($f['hasta']) && count($dias) < 93; $d->addDay()) {
            $r = $porDia[$d->format('Y-m-d')] ?? null;
            $dias[] = ['dia' => $d->format('d/m'), 'criticas' => (int) ($r->criticas ?? 0), 'total' => (int) ($r->total ?? 0)];
        }

        return [
            'total' => (int) $t->total,
            'alarmas' => (int) $t->alarmas,
            'criticas' => (int) $t->criticas,
            'abiertas' => (int) $t->abiertas,
            'mttr_s' => $t->mttr_s !== null ? (int) round($t->mttr_s) : null,
            'intermitentes' => $intermitentes,
            'top_equipos' => $topEquipos,
            'por_categoria' => $porCategoria,
            'por_dia' => $dias,
        ];
    }

    /** Todas las filas de la vista elegida, para exportar (máximo 20 000). */
    public function todasLasFilas(array $f)
    {
        return $this->filas($f, 20000)->getCollection();
    }

    public static function duracion(?float $s): string
    {
        if ($s === null) {
            return '—';
        }
        $s = (int) round($s);
        return match (true) {
            $s < 60 => "{$s} s",
            $s < 3600 => intdiv($s, 60) . ' min',
            $s < 86400 => intdiv($s, 3600) . ' h ' . intdiv($s % 3600, 60) . ' min',
            default => intdiv($s, 86400) . ' d ' . intdiv($s % 86400, 3600) . ' h',
        };
    }
}
