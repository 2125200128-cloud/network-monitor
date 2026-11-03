<?php

namespace App\Services;

use App\Http\Controllers\VirtualizacionController;
use App\Models\Dispositivo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Vista general del dashboard: estado de cada sector (red, firewalls, virtualización y sitios web), los incidentes
 * activos de todos juntos y lo que más requiere atención. Solo lecturas reales: lo que no tiene lectura vigente
 * queda en null y la vista dice "sin dato".
 *
 * Sectores: firewalls = FortiGate; virtualización = equipos con la ubicación de la sección Virtualización
 * (hosts ESXi y vCenter); web = sitios monitoreados; red = el resto de los equipos del sondeo.
 */
class ResumenSectores
{
    public const SECTORES = [
        'red' => ['nombre' => 'Red', 'ruta' => 'equipos.index'],
        'firewalls' => ['nombre' => 'Firewalls', 'ruta' => 'firewalls.index'],
        'virtualizacion' => ['nombre' => 'Virtualización', 'ruta' => 'virtualizacion.index'],
        'web' => ['nombre' => 'Servicios web', 'ruta' => 'servicios_web.index'],
    ];
    /** Lectura de la API de un ESXi más vieja que esto ya no es estado actual (igual que en Virtualización). */
    private const MINUTOS_ESXI_VIGENTE = 5;
    /** Igual que FirewallsService: el detalle de un FortiGate (túneles) más viejo que esto no se presenta. */
    private const MINUTOS_DETALLE_FW_VIGENTE = 15;
    /** Incidentes que caben en el dashboard; el resto, en Alertas. */
    private const MAX_INCIDENTES = 8;

    public function __construct(private DashboardMetricsService $metricas)
    {
    }

    /** ['sectores' => [...], 'incidentes' => [...], 'atencion' => [...]], compartido unos segundos entre pantallas. */
    public function resumen(): array
    {
        return Cache::remember('dashboard_sectores_v1', 10, fn () => $this->calcular($this->metricas->paneles()));
    }

    /** IDs de los FortiGate (también los usa la gráfica de tendencias). */
    public static function idsFirewalls(): array
    {
        return Cache::remember('dashboard_ids_firewalls_v1', 300, fn () => Dispositivo::with('telemetriaChasis')->get()
            ->filter(fn ($d) => $d->esFortigate())->pluck('id')->all());
    }

    private function calcular(array $paneles): array
    {
        $equipos = Dispositivo::with(['telemetriaChasis', 'ultimaMetrica'])->get();
        $fw = $equipos->filter(fn ($d) => $d->esFortigate());
        $virt = $equipos->where('ubicacion', VirtualizacionController::UBICACION)->diffKeys($fw);
        $red = $equipos->reject(fn ($d) => $fw->contains('id', $d->id) || $virt->contains('id', $d->id));

        $incidentes = $this->incidentes(array_flip($fw->pluck('id')->all()), array_flip($virt->pluck('id')->all()));
        $esxi = $this->esxi($virt);

        $sectores = [
            'red' => $this->red($red, $paneles),
            'firewalls' => $this->firewalls($fw),
            'virtualizacion' => $this->virtualizacion($virt, $esxi),
            'web' => $this->web($paneles['servicios_web']),
        ];
        // Semáforo de cada sector: lo deciden sus alarmas activas (las mismas de Alertas y de Telegram)
        foreach ($sectores as $k => &$s) {
            $propias = $incidentes->where('sector', $k);
            $s['criticas'] = $propias->where('severidad', 'critica')->count();
            $s['advertencias'] = $propias->count() - $s['criticas'];
            $s['tono'] = !$s['con_datos'] ? 'sin_dato' : ($s['criticas'] ? 'crit' : ($s['advertencias'] ? 'warn' : 'ok'));
        }
        unset($s);

        return [
            'sectores' => $sectores,
            'incidentes' => [
                'total' => $incidentes->count(),
                'criticas' => $incidentes->where('severidad', 'critica')->count(),
                'lista' => $incidentes->take(self::MAX_INCIDENTES)->values()->all(),
            ],
            'atencion' => $this->atencion($paneles, $esxi),
        ];
    }

    // =====================================================================
    //  Sectores
    // =====================================================================
    private function red(Collection $red, array $paneles): array
    {
        $g = DB::table('global_metrics')->latest('recorded_at')->first();
        $vigente = $g && Carbon::parse($g->recorded_at)->gt(now()->subMinutes(2));
        return [
            'con_datos' => $red->isNotEmpty(),
            'total' => $red->count(),
            'en_linea' => $red->where('estado', '!=', 'offline')->count(),
            'caidos' => $red->where('estado', 'offline')->count(),
            // global_metrics suma todos los equipos del sondeo (también firewalls y hosts)
            'trafico_mbps' => $vigente && $g->traffic_mbps !== null ? round((float) $g->traffic_mbps, 1) : null,
            'latencia_ms' => $vigente && $g->latency_ms !== null ? round((float) $g->latency_ms, 1) : null,
            'disponibilidad_24h' => $paneles['disponibilidad']['global']['24h']['pct'] ?? null,
        ];
    }

    private function firewalls(Collection $fw): array
    {
        $conSnmp = $fw->filter(fn ($d) => $this->respondeSnmp($d) && $d->estado !== 'offline');
        $tuneles = $conSnmp->flatMap(fn ($d) => $this->detalleFwVigente($d) ? ($d->telemetriaChasis->vpn_tuneles ?? []) : []);
        $sesiones = $conSnmp->map(fn ($d) => $d->ultimaMetrica?->conexiones_activas)->filter(fn ($v) => $v !== null);
        $cpus = $conSnmp->map(fn ($d) => $d->telemetriaChasis?->cpu_utilization)->filter(fn ($v) => $v !== null);
        return [
            'con_datos' => $fw->isNotEmpty(),
            'total' => $fw->count(),
            'en_linea' => $fw->where('estado', '!=', 'offline')->count(),
            'caidos' => $fw->where('estado', 'offline')->count(),
            'con_snmp' => $conSnmp->count(),
            'sesiones' => $sesiones->isEmpty() ? null : (int) $sesiones->sum(),
            'cpu_max' => $cpus->isEmpty() ? null : (int) $cpus->max(),
            'tuneles_total' => $tuneles->count(),
            'tuneles_arriba' => $tuneles->where('estado', 'up')->count(),
        ];
    }

    private function virtualizacion(Collection $virt, Collection $esxi): array
    {
        $hosts = $virt->reject(fn ($d) => $this->esVcenter($d));
        $vms = $esxi->flatMap(fn ($e) => $e['vms']);
        $peorDs = $this->datastores($esxi)->sortByDesc('usado_pct')->first();
        return [
            'con_datos' => $hosts->isNotEmpty(),
            'hosts' => $hosts->count(),
            'en_linea' => $hosts->where('estado', '!=', 'offline')->count(),
            'caidos' => $hosts->where('estado', 'offline')->count(),
            'leidos' => $esxi->count(),
            'cpu_prom' => $esxi->whereNotNull('cpu_pct')->isEmpty() ? null : round($esxi->whereNotNull('cpu_pct')->avg('cpu_pct')),
            'ram_prom' => $esxi->whereNotNull('ram_pct')->isEmpty() ? null : round($esxi->whereNotNull('ram_pct')->avg('ram_pct')),
            'vms_total' => $esxi->isEmpty() ? null : $vms->count(),
            'vms_encendidas' => $esxi->isEmpty() ? null : $vms->where('estado', 'poweredOn')->count(),
            'datastore_max' => $peorDs ? ['nombre' => $peorDs['nombre'], 'pct' => $peorDs['usado_pct']] : null,
        ];
    }

    private function web(array $w): array
    {
        return [
            'con_datos' => ($w['total'] ?? 0) > 0,
            'total' => $w['total'] ?? 0,
            'en_linea' => $w['online'] ?? 0,
            'lentos' => $w['warning'] ?? 0,
            'caidos' => $w['offline'] ?? 0,
            'respuesta_ms' => $w['latencia_prom'] ?? null,
            'disponibilidad_24h' => $w['disponibilidad_24h'] ?? null,
            'ssl_proximo' => $w['ssl_proximo'] ?? null,
        ];
    }

    // =====================================================================
    //  Incidentes activos de todos los sectores
    // =====================================================================
    private function incidentes(array $fwIds, array $virtIds): Collection
    {
        return collect(AlarmDiagnosisService::diagnosticoCompartido())
            ->reject(fn ($a) => ($a['severidad'] ?? '') === 'informativa')
            ->map(function ($a) use ($fwIds, $virtIds) {
                try {
                    $inicio = Carbon::createFromFormat('d/m/Y H:i:s', (string) ($a['fecha_falla'] ?? ''));
                } catch (\Throwable) {
                    $inicio = null;
                }
                $id = $a['dispositivo_id'] ?? -1;
                $cat = $a['categoria'] ?? '';
                return [
                    'sector' => match (true) {
                        $cat === 'servicio_web' => 'web',
                        $cat === 'virtualizacion' || isset($virtIds[$id]) => 'virtualizacion',
                        isset($fwIds[$id]) => 'firewalls',
                        default => 'red',
                    },
                    'severidad' => ($a['severidad'] ?? '') === 'critica' ? 'critica' : 'advertencia',
                    'titulo' => $a['titulo'] ?? '',
                    'equipo' => $a['dispositivo'] ?? '',
                    'ip' => $a['ip'] ?? '',
                    'link' => $a['link'] ?? null,
                    'inicio_ts' => $inicio?->timestamp ?? 0,
                    'hace' => $inicio ? $this->duracion((int) $inicio->diffInSeconds(now())) : null,
                ];
            })
            // Lo crítico primero; dentro de cada severidad, lo más reciente
            ->sortBy(fn ($a) => [$a['severidad'] === 'critica' ? 0 : 1, -$a['inicio_ts']])
            ->values();
    }

    // =====================================================================
    //  Lo que más preocupa ahora: lo más cargado de cada sector, aunque aún no sea alarma
    //  (las fallas ya están en los incidentes). Cada grupo: hasta 3 lecturas, de la más alta a la más baja.
    // =====================================================================
    private function atencion(array $paneles, Collection $esxi): array
    {
        // Tono por umbral: [advertencia, crítico]; en los certificados, menos días es peor
        $tono = fn (?float $v, array $u, bool $menorEsPeor = false) => $v === null ? null
            : ($menorEsPeor ? ($v <= $u[1] ? 'crit' : ($v <= $u[0] ? 'warn' : 'ok')) : ($v >= $u[1] ? 'crit' : ($v >= $u[0] ? 'warn' : 'ok')));
        $equipo = fn ($id) => route('dispositivos.show', $id);
        $item = fn (string $nombre, ?string $detalle, float $valor, string $texto, ?string $t, float $barra, ?string $link) =>
            compact('nombre', 'detalle', 'valor', 'texto', 'barra', 'link') + ['tono' => $t];

        $grupos = [];
        $grupos[] = ['sector' => 'red', 'titulo' => 'Enlaces más cargados', 'vacio' => 'Sin uso medido en los puertos',
            'items' => collect($paneles['puertos']['saturados'])->take(3)->map(fn ($p) => $item(
                $p['dispositivo'], $p['puerto'] . ($p['alias'] ? ' · ' . $p['alias'] : ''), $p['util'], "{$p['util']}%",
                $tono($p['util'], [80, 95]), $p['util'], $equipo($p['disp_id'])))->all()];
        $grupos[] = ['sector' => 'red', 'titulo' => 'Mayor CPU', 'vacio' => 'Sin lecturas de CPU',
            'items' => collect($paneles['top_equipos']['cpu'])->take(3)->map(fn ($c) => $item(
                $c['dispositivo'], null, $c['valor'], "{$c['valor']}%", $tono($c['valor'], [80, 90]), $c['valor'], $equipo($c['disp_id'])))->all()];
        $grupos[] = ['sector' => 'virtualizacion', 'titulo' => 'Hosts ESXi más cargados', 'vacio' => 'Sin lectura de la API de los hosts',
            'items' => $esxi->map(fn ($e) => $e + ['max' => max($e['cpu_pct'] ?? 0, $e['ram_pct'] ?? 0)])
                ->sortByDesc('max')->take(3)->map(fn ($e) => $item(
                    $e['nombre'], 'CPU ' . round($e['cpu_pct'] ?? 0) . '% · memoria ' . round($e['ram_pct'] ?? 0) . '%', $e['max'],
                    round($e['max']) . '%', ($e['ram_pct'] ?? 0) >= ($e['cpu_pct'] ?? 0) ? $tono($e['max'], [85, 95]) : $tono($e['max'], [80, 90]),
                    $e['max'], route('virtualizacion.index')))->values()->all()];
        $grupos[] = ['sector' => 'virtualizacion', 'titulo' => 'Datastores más llenos', 'vacio' => 'Sin lectura de datastores',
            'items' => $this->datastores($esxi)->sortByDesc('usado_pct')->take(3)->map(fn ($ds) => $item(
                $ds['nombre'], null, $ds['usado_pct'], "{$ds['usado_pct']}%", $tono($ds['usado_pct'], [80, 90]), $ds['usado_pct'],
                route('virtualizacion.index')))->values()->all()];
        $grupos[] = ['sector' => 'web', 'titulo' => 'Certificados SSL más próximos', 'vacio' => 'Sin certificados medidos',
            'items' => DB::table('servicios_web')->where('es_activo', true)->whereNotNull('ssl_dias_expiracion')
                ->orderBy('ssl_dias_expiracion')->limit(3)->get(['nombre', 'ssl_dias_expiracion'])
                // Barra: lo que falta para vencer dentro de una ventana de 90 días (llena = vence ya)
                ->map(fn ($s) => $item($s->nombre, null, $s->ssl_dias_expiracion,
                    $s->ssl_dias_expiracion < 0 ? 'Vencido' : $s->ssl_dias_expiracion . ' días', $tono($s->ssl_dias_expiracion, [30, 7], true),
                    max(0, min(100, (90 - $s->ssl_dias_expiracion) / 90 * 100)), route('servicios_web.index')))->all()];
        return $grupos;
    }

    // =====================================================================
    //  Auxiliares
    // =====================================================================

    /** Lectura vigente de la API de cada host ESXi: CPU, memoria, VMs y datastores. */
    private function esxi(Collection $virt): Collection
    {
        $nombres = $virt->pluck('nombre', 'id');
        return DB::table('esxi_estado')
            ->whereIn('dispositivo_id', $nombres->keys())
            ->where('leido_en', '>=', now()->subMinutes(self::MINUTOS_ESXI_VIGENTE))
            ->get(['dispositivo_id', 'cpu_pct', 'ram_pct', 'vms', 'datastores'])
            ->map(fn ($e) => [
                'id' => $e->dispositivo_id,
                'nombre' => $nombres[$e->dispositivo_id],
                'cpu_pct' => $e->cpu_pct !== null ? (float) $e->cpu_pct : null,
                'ram_pct' => $e->ram_pct !== null ? (float) $e->ram_pct : null,
                'vms' => json_decode($e->vms ?? '[]', true) ?: [],
                'datastores' => json_decode($e->datastores ?? '[]', true) ?: [],
            ]);
    }

    /** Datastores sin repetir los compartidos (por URL); del espacio libre se toma el menor que reporte un host. */
    private function datastores(Collection $esxi): Collection
    {
        return $esxi->flatMap(fn ($e) => collect($e['datastores'])->map(fn ($ds) => $ds + ['host' => $e['nombre']]))
            ->groupBy(fn ($ds) => $ds['url'] ?? $ds['host'] . '|' . $ds['nombre'])
            ->map(function ($g) {
                $ds = $g->first();
                $libre = $g->pluck('libre')->filter(fn ($v) => $v !== null)->min();
                return ['nombre' => $ds['nombre'], 'usado_pct' => !empty($ds['capacidad']) && $libre !== null
                    ? round(($ds['capacidad'] - $libre) / $ds['capacidad'] * 100, 1) : null];
            })
            ->whereNotNull('usado_pct')->values();
    }

    private function respondeSnmp(Dispositivo $d): bool
    {
        $u = $d->telemetriaChasis?->uptime_str;
        return $u && !in_array($u, ['Sin respuesta SNMP', 'Inaccesible'], true);
    }

    private function detalleFwVigente(Dispositivo $d): bool
    {
        $m = $d->telemetriaChasis?->detalle_medido_en;
        return $m && $m->gt(now()->subMinutes(self::MINUTOS_DETALLE_FW_VIGENTE));
    }

    private function esVcenter(Dispositivo $d): bool
    {
        return str_contains(strtolower($d->nombre), 'vcenter');
    }

    private function duracion(int $seg): string
    {
        return match (true) {
            $seg < 60 => "{$seg} s",
            $seg < 3600 => intdiv($seg, 60) . ' min',
            $seg < 86400 => intdiv($seg, 3600) . ' h ' . intdiv($seg % 3600, 60) . ' min',
            default => intdiv($seg, 86400) . ' d ' . intdiv($seg % 86400, 3600) . ' h',
        };
    }
}
