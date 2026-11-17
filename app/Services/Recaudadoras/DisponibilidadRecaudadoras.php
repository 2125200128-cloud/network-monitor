<?php

namespace App\Services\Recaudadoras;

use App\Models\Recaudadora;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Disponibilidad de comunicación: % del tiempo monitoreado sin un evento «sin_comunicacion» abierto. La falla de red
 * interna no cuenta como caída (la recaudadora tiene enlace). El tiempo monitoreado empieza cuando se dio de alta.
 */
class DisponibilidadRecaudadoras
{
    /** @return array<int, float|null> recaudadora_id → % (null si aún no hay tiempo monitoreado en la ventana) */
    public static function porRecaudadora(CarbonInterface $desde, CarbonInterface $hasta, ?array $ids = null): array
    {
        return array_map(fn ($r) => $r['disponibilidad'], self::resumen($desde, $hasta, $ids));
    }

    /**
     * Cifras del periodo de cada recaudadora: disponibilidad (%), caídas (eventos sin comunicación que tocan la
     * ventana), segundos sin comunicación, la caída más larga, segundos con falla de red interna y segundos monitoreados.
     *
     * @return array<int, array{disponibilidad: float|null, caidas: int, segundos_caida: int, mas_larga: int, segundos_lan: int, monitoreado: int}>
     */
    public static function resumen(CarbonInterface $desde, CarbonInterface $hasta, ?array $ids = null): array
    {
        $recas = Recaudadora::query()->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->get(['id', 'created_at', 'ip_administrativa', 'ip_loopback', 'ultimo_chequeo']);
        $eventos = self::eventos($desde, $hasta, $ids);

        $out = [];
        foreach ($recas as $r) {
            $ev = $eventos[$r->id] ?? [];
            $caida = $ev['sin_comunicacion'] ?? ['segundos' => 0, 'n' => 0, 'mas_larga' => 0];
            $fila = ['disponibilidad' => null, 'caidas' => $caida['n'], 'segundos_caida' => $caida['segundos'],
                'mas_larga' => $caida['mas_larga'], 'segundos_lan' => $ev['lan']['segundos'] ?? 0, 'monitoreado' => 0];
            // Sin IP o nunca revisada: no hay qué medir. Si el monitoreo se detuvo, la ventana termina en su última revisión
            if (($r->ip_administrativa || $r->ip_loopback) && $r->ultimo_chequeo) {
                $inicio = $r->created_at && $r->created_at->gt($desde) ? $r->created_at : $desde;
                $fin = min($hasta->getTimestamp(), self::hastaRevisado($r->ultimo_chequeo));
                $total = $fin - $inicio->getTimestamp();
                if ($total >= 60) {
                    $fila['monitoreado'] = $total;
                    $fila['disponibilidad'] = round(max(0, 100 * (1 - min($caida['segundos'], $total) / $total)), 2);
                }
            }
            $out[$r->id] = $fila;
        }
        return $out;
    }

    /** Disponibilidad por día (últimos $dias días, hoy incluido) de una recaudadora: ['2026-11-13' => 99.8, …]. */
    public static function porDia(Recaudadora $r, int $dias = 30): array
    {
        $out = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $desde = now()->startOfDay()->subDays($i);
            $hasta = $i === 0 ? now() : $desde->copy()->endOfDay();
            $out[$desde->toDateString()] = self::porRecaudadora($desde, $hasta, [$r->id])[$r->id] ?? null;
        }
        return $out;
    }

    /**
     * Hasta cuándo hay certeza del estado: un minuto después de la última revisión (lo que tarda la siguiente) y nunca
     * después de ahora. Una caída abierta no se alarga si el temporizador dejó de correr: no se sabe qué pasó después.
     */
    public static function hastaRevisado($ultimoChequeo): int
    {
        return $ultimoChequeo ? min(now()->getTimestamp(), strtotime((string) $ultimoChequeo) + 60) : now()->getTimestamp();
    }

    /**
     * Eventos de la sede (sin comunicación y falla de red interna) dentro de la ventana, recortados a ella:
     * [recaudadora_id][tipo] = [segundos, n, mas_larga]. La caída más larga se mide completa, no recortada.
     */
    private static function eventos(CarbonInterface $desde, CarbonInterface $hasta, ?array $ids): array
    {
        $eventos = DB::table('recaudadora_eventos as e')->join('recaudadoras as r', 'r.id', '=', 'e.recaudadora_id')
            ->whereNull('e.equipo_id')->whereIn('e.tipo', ['sin_comunicacion', 'lan'])
            ->where('e.inicio', '<', $hasta)->where(fn ($q) => $q->whereNull('e.fin')->orWhere('e.fin', '>', $desde))
            ->when($ids, fn ($q) => $q->whereIn('e.recaudadora_id', $ids))
            ->get(['e.recaudadora_id', 'e.tipo', 'e.inicio', 'e.fin', 'r.ultimo_chequeo']);

        $out = [];
        foreach ($eventos as $e) {
            $inicio = strtotime($e->inicio);
            $termino = $e->fin ? strtotime($e->fin) : self::hastaRevisado($e->ultimo_chequeo);
            $dentro = min($hasta->getTimestamp(), $termino) - max($desde->getTimestamp(), $inicio);
            if ($dentro <= 0) {
                continue;
            }
            $t = &$out[$e->recaudadora_id][$e->tipo];
            $t ??= ['segundos' => 0, 'n' => 0, 'mas_larga' => 0];
            $t['segundos'] += $dentro;
            $t['n']++;
            $t['mas_larga'] = max($t['mas_larga'], $termino - $inicio);
            unset($t);
        }
        return $out;
    }

    /** Eventos sin comunicación del periodo (los más recientes primero), para el detalle del reporte. */
    public static function caidas(CarbonInterface $desde, CarbonInterface $hasta, int $limite = 1000)
    {
        return DB::table('recaudadora_eventos as e')->join('recaudadoras as r', 'r.id', '=', 'e.recaudadora_id')
            ->whereNull('e.equipo_id')->where('e.tipo', 'sin_comunicacion')
            ->where('e.inicio', '<', $hasta)->where(fn ($q) => $q->whereNull('e.fin')->orWhere('e.fin', '>', $desde))
            ->orderByDesc('e.inicio')->limit($limite)
            ->get(['e.recaudadora_id', 'e.inicio', 'e.fin', 'r.tipo', 'r.numero', 'r.nombre', 'r.municipio', 'r.ultimo_chequeo']);
    }
}
