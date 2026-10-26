<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use App\Support\Sql;
use App\Services\FirewallsService;
use App\Services\VcenterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sección Virtualización: hosts ESXi y vCenter.
 * - Ping (estado, latencia, disponibilidad): siempre, del sondeo general.
 * - Cada ESXi por su API (worker/esxi_collector.py → esxi_estado / esxi_metricas): CPU, memoria, hardware,
 *   sensores, alarmas, VMs y datastores.
 * - vCenter (opcional, Ajustes → vCenter): clústeres con HA/DRS.
 */
class VirtualizacionController extends Controller
{
    public const UBICACION = 'Centro de datos · Virtualización';
    /** Una lectura del colector más vieja que esto ya no se presenta como estado actual. */
    private const MINUTOS_LECTURA_VIGENTE = 5;
    private const HORAS_HISTORIA = 6;
    private const BLOQUE_SEG = 300;

    public function index(FirewallsService $metricas, VcenterService $vcenter)
    {
        $equipos = Dispositivo::with('ultimaMetrica')->where('ubicacion', self::UBICACION)->orderBy('nombre')->get();
        $ids = $equipos->pluck('id')->all();
        $clave = md5(implode(',', $ids));
        $ping = Cache::remember("virt_historia_{$clave}", 60, fn () => $metricas->historia($ids));
        $disponibilidad = Cache::remember("virt_disponibilidad_{$clave}", 60, fn () => $metricas->disponibilidad24h($ids));
        $historiaEsxi = Cache::remember("virt_esxi_historia_{$clave}", 60, fn () => $this->historiaEsxi($ids));
        $estados = DB::table('esxi_estado')->whereIn('dispositivo_id', $ids)->get()->keyBy('dispositivo_id');

        $filas = $equipos->map(function ($d) use ($ping, $disponibilidad, $historiaEsxi, $estados) {
            $e = $estados[$d->id] ?? null;
            $leido = $e?->leido_en ? Carbon::parse($e->leido_en) : null;
            $vigente = $leido && $leido->gt(now()->subMinutes(self::MINUTOS_LECTURA_VIGENTE));
            $json = fn ($v) => json_decode($v ?? 'null', true) ?: [];
            $vms = $vigente ? $json($e->vms) : [];
            return [
                'id' => $d->id,
                'nombre' => $d->nombre,
                'ip' => $d->ip,
                'estado' => $d->estado,
                'es_vcenter' => str_contains(strtolower($d->nombre), 'vcenter'),
                'ping_ms' => $d->ultimaMetrica?->ping_ms,
                'perdida_pct' => $d->ultimaMetrica?->packet_loss,
                'disponibilidad_24h' => $disponibilidad[$d->id] ?? null,
                'ping_historia' => $ping[$d->id] ?? [],
                'historia' => $historiaEsxi[$d->id] ?? [],
                // Lectura de la API del host (solo si es reciente; si no, se dice desde cuándo no se lee)
                'api' => $vigente ? [
                    'conexion' => $e->conexion,
                    'mantenimiento' => (bool) $e->mantenimiento,
                    'salud' => $e->estado_general,
                    'cpu_pct' => $e->cpu_pct, 'ram_pct' => $e->ram_pct,
                    'cpu_ghz_usado' => $e->cpu_mhz_usado !== null ? round($e->cpu_mhz_usado / 1000, 1) : null,
                    'cpu_ghz_total' => $e->cpu_mhz_total !== null ? round($e->cpu_mhz_total / 1000, 1) : null,
                    'ram_gb_usado' => $e->ram_mb_usado !== null ? round($e->ram_mb_usado / 1024) : null,
                    'ram_gb_total' => $e->ram_mb_total !== null ? round($e->ram_mb_total / 1024) : null,
                    'uptime' => $e->uptime_seg !== null ? $this->duracion((int) $e->uptime_seg) : null,
                    'hardware' => trim(($e->fabricante ?? '') . ' ' . ($e->modelo ?? '')) ?: null,
                    'cpu_modelo' => $e->cpu_modelo, 'nucleos' => $e->nucleos, 'hilos' => $e->hilos,
                    'version' => $e->version,
                    'sensores' => $json($e->sensores),
                    'alarmas' => $json($e->alarmas),
                    'vms' => $vms,
                    'datastores' => $json($e->datastores),
                ] : null,
                'api_error' => $e?->error,
                'api_leido_hace' => $leido?->locale('es')->diffForHumans(),
            ];
        });

        // VMs y datastores de todos los hosts leídos. Un datastore compartido aparece una vez (se agrupa por su URL,
        // no por nombre). Cada host reporta su propia copia del espacio libre y no siempre coinciden: se usa la menor
        // y se guarda el rango para mostrarlo.
        $vms = $filas->flatMap(fn ($f) => collect($f['api']['vms'] ?? [])->map(fn ($v) => $v + ['host' => $f['nombre'], 'host_id' => $f['id']]));
        $datastores = $filas->flatMap(fn ($f) => collect($f['api']['datastores'] ?? [])->map(fn ($ds) => $ds + ['host' => $f['nombre']]))
            ->groupBy(fn ($ds) => $ds['url'] ?? $ds['host'] . '|' . $ds['nombre'])->map(function ($g) {
                $ds = $g->first();
                $libres = $g->pluck('libre')->filter(fn ($v) => $v !== null);
                $ds['libre'] = $libres->min();
                $libreMax = $libres->max();
                $usado = $ds['capacidad'] && $ds['libre'] !== null ? round(($ds['capacidad'] - $ds['libre']) / $ds['capacidad'] * 100, 1) : null;
                $difieren = $ds['capacidad'] && $libreMax !== null && ($libreMax - $ds['libre']) / $ds['capacidad'] > 0.01;
                return $ds + ['usado_pct' => $usado, 'libre_max' => $difieren ? $libreMax : null, 'hosts' => $g->pluck('host')->unique()->count()];
            })->sortByDesc('usado_pct')->values();
        $alarmas = $filas->flatMap(fn ($f) => collect($f['api']['alarmas'] ?? [])->map(fn ($a) => $a + ['host' => $f['nombre'], 'host_id' => $f['id']]))
            ->sortBy(fn ($a) => $a['estado'] === 'red' ? 0 : 1)->values();
        $sensoresMal = $filas->flatMap(fn ($f) => collect($f['api']['sensores'] ?? [])->whereIn('estado', ['yellow', 'red'])
            ->map(fn ($s) => $s + ['host' => $f['nombre'], 'host_id' => $f['id']]))->values();
        $hostsApi = $filas->filter(fn ($f) => $f['api']);

        $vc = $vcenter->resumen();
        $ultimaLectura = $estados->max('leido_en');

        return view('virtualizacion.index', [
            'filas' => $filas->where('es_vcenter', false)->values()->all(),
            'vcenter_fila' => $filas->firstWhere('es_vcenter', true),
            'atencion' => $this->atencion($filas, $sensoresMal, $alarmas, $datastores, $vms, $vc),
            'ultima_lectura' => $ultimaLectura ? Carbon::parse($ultimaLectura)->locale('es')->diffForHumans() : null,
            'vms' => $vms->sortBy(fn ($v) => [$v['estado'] === 'poweredOn' ? 1 : 0, strtolower($v['nombre'] ?? '')])->values()->all(),
            'datastores' => $datastores->all(),
            'alarmas' => $alarmas->all(),
            'sensores_mal' => $sensoresMal->all(),
            'clusters' => ($vc['ok'] ?? false) ? ($vc['clusters'] ?? []) : [],
            'vcenter' => $vc,
            'hay_api' => $hostsApi->isNotEmpty(),
            'kpi' => [
                'hosts' => $filas->where('es_vcenter', false)->count(),
                'hosts_en_linea' => $filas->where('es_vcenter', false)->where('estado', '!=', 'offline')->count(),
                'hosts_leidos' => $hostsApi->count(),
                'cpu_prom' => $hostsApi->avg(fn ($f) => $f['api']['cpu_pct']),
                'ram_prom' => $hostsApi->avg(fn ($f) => $f['api']['ram_pct']),
                'vms_total' => $vms->count(),
                'vms_encendidas' => $vms->where('estado', 'poweredOn')->count(),
                'ds_capacidad' => $datastores->sum('capacidad'),
                'ds_libre' => $datastores->sum('libre'),
                'sensores' => $hostsApi->sum(fn ($f) => count($f['api']['sensores'])),
                'sensores_mal' => $sensoresMal->count(),
                'sensores_rojos' => $sensoresMal->where('estado', 'red')->count(),
                'alarmas' => $alarmas->count(),
                'alarmas_rojas' => $alarmas->where('estado', 'red')->count(),
                'disponibilidad_24h' => $filas->whereNotNull('disponibilidad_24h')->avg('disponibilidad_24h'),
            ],
        ]);
    }

    /**
     * Lo que requiere atención ahora, de lo leído (nada calculado ni supuesto): primero lo crítico.
     * Cada punto: nivel (crit|warn), qué pasa, dónde y un detalle.
     */
    private function atencion($filas, $sensoresMal, $alarmas, $datastores, $vms, array $vc): array
    {
        $lista = collect();
        $add = fn ($nivel, $que, $donde, $detalle = null) => $lista->push(compact('nivel', 'que', 'donde', 'detalle'));

        foreach ($filas as $f) {
            if ($f['estado'] === 'offline') {
                $add('crit', 'Sin respuesta a ping', $f['nombre'], $f['ip']);
            }
            $a = $f['api'];
            if ($f['es_vcenter']) {
                continue;
            }
            if (!$a) {
                $add('warn', 'No se está leyendo su API', $f['nombre'], $f['api_error'] ?: ($f['api_leido_hace'] ? 'última lectura ' . $f['api_leido_hace'] : 'aún sin lectura'));
                continue;
            }
            if ($a['conexion'] && $a['conexion'] !== 'connected') {
                $add('crit', 'Host ' . ($a['conexion'] === 'notResponding' ? 'sin respuesta' : 'desconectado') . ' del vCenter', $f['nombre']);
            }
            if ($a['mantenimiento']) {
                $add('warn', 'En modo mantenimiento', $f['nombre']);
            }
            if ($a['cpu_pct'] !== null && $a['cpu_pct'] >= 80) {
                $add($a['cpu_pct'] >= 90 ? 'crit' : 'warn', "CPU al {$a['cpu_pct']}%", $f['nombre'], "{$a['cpu_ghz_usado']} de {$a['cpu_ghz_total']} GHz");
            }
            if ($a['ram_pct'] !== null && $a['ram_pct'] >= 85) {
                $add($a['ram_pct'] >= 95 ? 'crit' : 'warn', "Memoria al {$a['ram_pct']}%", $f['nombre'], "{$a['ram_gb_usado']} de {$a['ram_gb_total']} GB");
            }
        }
        foreach ($sensoresMal as $s) {
            $add($s['estado'] === 'red' ? 'crit' : 'warn', 'Sensor ' . ($s['estado'] === 'red' ? 'en rojo' : 'en amarillo') . ': ' . $s['nombre'], $s['host'],
                trim(($s['tipo'] ?? '') . ($s['valor'] !== null ? " · {$s['valor']} {$s['unidad']}" : '')));
        }
        foreach ($alarmas as $al) {
            $add($al['estado'] === 'red' ? 'crit' : 'warn', 'Alarma VMware: ' . $al['nombre'], $al['host']);
        }
        foreach ($datastores as $ds) {
            if ($ds['accesible'] === false) {
                $add('crit', 'Datastore inaccesible: ' . $ds['nombre'], $ds['hosts'] . ' host(s)');
            } elseif ($ds['usado_pct'] !== null && $ds['usado_pct'] >= 80) {
                $add($ds['usado_pct'] >= 90 ? 'crit' : 'warn', "Datastore al {$ds['usado_pct']}%: {$ds['nombre']}", $ds['hosts'] . ' host(s)',
                    'libre ' . round(($ds['libre'] ?? 0) / 1024 ** 4, 2) . ' TB' . ($ds['libre_max'] ? ' (otros hosts reportan hasta ' . round($ds['libre_max'] / 1024 ** 4, 2) . ' TB)' : ''));
            }
        }
        foreach ($vms->whereIn('salud', ['yellow', 'red']) as $vm) {
            $add($vm['salud'] === 'red' ? 'crit' : 'warn', 'VM con estado ' . ($vm['salud'] === 'red' ? 'rojo' : 'amarillo') . ': ' . $vm['nombre'], $vm['host']);
        }
        if (!($vc['ok'] ?? false) && ($vc['error'] ?? '') !== 'sin_configurar') {
            $add('warn', 'No se puede leer el vCenter', 'vCenter Server', $vc['error'] ?? null);
        }
        return $lista->sortBy(fn ($x) => $x['nivel'] === 'crit' ? 0 : 1)->values()->all();
    }

    /** CPU y memoria de cada host en bloques de 5 min de las últimas horas (promedio de las lecturas reales). */
    private function historiaEsxi(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $bloque = 'FLOOR(' . Sql::epoch('fecha') . ' / ' . self::BLOQUE_SEG . ') * ' . self::BLOQUE_SEG;
        return DB::table('esxi_metricas')
            ->whereIn('dispositivo_id', $ids)->where('fecha', '>=', now()->subHours(self::HORAS_HISTORIA))
            ->groupBy('dispositivo_id', DB::raw($bloque))->orderBy(DB::raw($bloque))
            ->get(['dispositivo_id', DB::raw("{$bloque} AS t"), DB::raw('AVG(cpu_pct) AS cpu'), DB::raw('AVG(ram_pct) AS ram')])
            ->groupBy('dispositivo_id')
            ->map(fn ($g) => $g->map(fn ($x) => [
                'h' => gmdate('H:i', (int) $x->t),   // fecha guardada en hora local sin zona (igual que metricas_red)
                'cpu' => $x->cpu === null ? null : round((float) $x->cpu, 1),
                'ram' => $x->ram === null ? null : round((float) $x->ram, 1),
            ])->values()->all())->all();
    }

    private function duracion(int $seg): string
    {
        $d = intdiv($seg, 86400);
        $h = intdiv($seg % 86400, 3600);
        $m = intdiv($seg % 3600, 60);
        return $d ? "{$d}d {$h}h" : ($h ? "{$h}h {$m}m" : "{$m}m");
    }
}
