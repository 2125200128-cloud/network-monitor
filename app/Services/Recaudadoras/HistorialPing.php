<?php

namespace App\Services\Recaudadoras;

use App\Models\Recaudadora;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Historial de ping de las recaudadoras (gráficas de su ficha): guarda cada revisión, la resume por hora y arma
 * la serie de un periodo en intervalos parejos. Hasta 7 días sale del detalle por minuto; más allá, del resumen por hora.
 */
class HistorialPing
{
    public const DIAS_DETALLE = 8;
    public const DIAS_HORA = 400;

    /**
     * Periodo → [segundos que abarca, segundos de cada intervalo de la gráfica, fuente]. El intervalo más corto es de
     * 2 min: la revisión corre cada minuto pero no siempre en el mismo segundo (tarda en hacer los pings), así que con
     * intervalos de 1 min algunos quedarían vacíos y la gráfica mostraría cortes que no existen.
     */
    public const RANGOS = [
        '6h' => [6 * 3600, 120, 'detalle'],
        '24h' => [86400, 300, 'detalle'],
        '7d' => [7 * 86400, 1800, 'detalle'],
        '30d' => [30 * 86400, 7200, 'hora'],
        '90d' => [90 * 86400, 21600, 'hora'],
    ];

    /** @param list<array{recaudadora_id:int, medido_en:Carbon, ok:bool, admin_ok:?bool, loopback_ok:?bool, admin_ms:?float, loopback_ms:?float}> $filas */
    public static function registrar(array $filas): void
    {
        if (!$filas) {
            return;
        }
        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('recaudadora_mediciones')->insert($lote);
        }
        self::consolidar(now()->subHour()->startOfHour());
        // Limpieza una vez por hora
        if (Cache::add('recas_mediciones_limpieza', 1, now()->addHour())) {
            DB::table('recaudadora_mediciones')->where('medido_en', '<', now()->subDays(self::DIAS_DETALLE))->delete();
            DB::table('recaudadora_mediciones_hora')->where('hora', '<', now()->subDays(self::DIAS_HORA))->delete();
        }
    }

    /** Recalcula el resumen por hora desde $desde (la hora anterior y la actual en cada revisión). */
    public static function consolidar(Carbon $desde): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO recaudadora_mediciones_hora
                (recaudadora_id, hora, muestras, ok_muestras, admin_min, admin_avg, admin_max, loopback_min, loopback_avg, loopback_max,
                 admin_perdida_avg, admin_perdida_max, loopback_perdida_avg, loopback_perdida_max)
            SELECT recaudadora_id, date_trunc('hour', medido_en), count(*), count(*) FILTER (WHERE ok),
                   min(admin_ms), avg(admin_ms), max(admin_ms), min(loopback_ms), avg(loopback_ms), max(loopback_ms),
                   avg(admin_perdida), max(admin_perdida), avg(loopback_perdida), max(loopback_perdida)
            FROM recaudadora_mediciones
            WHERE medido_en >= ?
            GROUP BY 1, 2
            ON CONFLICT (recaudadora_id, hora) DO UPDATE SET
                muestras = EXCLUDED.muestras, ok_muestras = EXCLUDED.ok_muestras,
                admin_min = EXCLUDED.admin_min, admin_avg = EXCLUDED.admin_avg, admin_max = EXCLUDED.admin_max,
                loopback_min = EXCLUDED.loopback_min, loopback_avg = EXCLUDED.loopback_avg, loopback_max = EXCLUDED.loopback_max,
                admin_perdida_avg = EXCLUDED.admin_perdida_avg, admin_perdida_max = EXCLUDED.admin_perdida_max,
                loopback_perdida_avg = EXCLUDED.loopback_perdida_avg, loopback_perdida_max = EXCLUDED.loopback_perdida_max
            SQL, [$desde]);
    }

    /**
     * Serie de un periodo: un punto por intervalo (null donde no hubo revisiones) y el resumen del periodo.
     * @return array{rango:string, intervalo_s:int, puntos:list<array>, resumen:array}
     */
    public static function serie(Recaudadora $r, string $rango): array
    {
        [$abarca, $paso, $fuente] = self::RANGOS[$rango] ?? self::RANGOS['24h'];
        $hasta = now();
        $desde = Carbon::createFromTimestamp(intdiv($hasta->getTimestamp() - $abarca, $paso) * $paso + $paso, $hasta->getTimezone());
        // Si el historial de esta recaudadora empezó dentro del periodo, la gráfica empieza ahí (sin un tramo vacío
        // que parezca monitoreo detenido)
        // (el resumen por hora es el que llega más atrás; el detalle por minuto solo afina el minuto de esa primera hora)
        $primera = DB::table('recaudadora_mediciones_hora')->where('recaudadora_id', $r->id)->min('hora');
        $primeraDetalle = DB::table('recaudadora_mediciones')->where('recaudadora_id', $r->id)->min('medido_en');
        if ($primera && $primeraDetalle && Carbon::parse($primeraDetalle)->copy()->startOfHour()->eq(Carbon::parse($primera))) {
            $primera = $primeraDetalle;
        }
        if ($primera && Carbon::parse($primera)->gt($desde)) {
            $desde = Carbon::createFromTimestamp(intdiv(Carbon::parse($primera)->getTimestamp(), $paso) * $paso, $hasta->getTimezone());
        }

        // Cada fila, a la forma del resumen por hora: muestras, ok y, por métrica, [mín, suma, máx, cuántas con dato].
        // Métricas: a/l = latencia (ms) de la IP administrativa / loopback; pa/pl = su pérdida de paquetes (%).
        $metricas = ['a' => 'admin', 'l' => 'loopback', 'pa' => 'admin_perdida', 'pl' => 'loopback_perdida'];
        $uno = fn ($v) => $v === null ? [null, 0.0, null, 0] : [(float) $v, (float) $v, (float) $v, 1];
        // De una hora: el promedio pesa por las muestras de la hora (aproximación suficiente para la gráfica)
        $hora = fn ($min, $avg, $max, int $n) => $avg === null ? [null, 0.0, null, 0] : [(float) ($min ?? $avg), (float) $avg * $n, (float) $max, $n];
        if ($fuente === 'detalle') {
            $filas = DB::table('recaudadora_mediciones')->where('recaudadora_id', $r->id)->where('medido_en', '>=', $desde)
                ->orderBy('medido_en')->get(['medido_en as t', 'ok', 'admin_ms', 'loopback_ms', 'admin_perdida', 'loopback_perdida'])
                ->map(fn ($f) => ['t' => Carbon::parse($f->t), 'n' => 1, 'ok' => (int) $f->ok,
                    'a' => $uno($f->admin_ms), 'l' => $uno($f->loopback_ms), 'pa' => $uno($f->admin_perdida), 'pl' => $uno($f->loopback_perdida)]);
        } else {
            $filas = DB::table('recaudadora_mediciones_hora')->where('recaudadora_id', $r->id)->where('hora', '>=', $desde->copy()->startOfHour())
                ->orderBy('hora')->get()
                ->map(fn ($f) => ['t' => Carbon::parse($f->hora), 'n' => (int) $f->muestras, 'ok' => (int) $f->ok_muestras,
                    'a' => $hora($f->admin_min, $f->admin_avg, $f->admin_max, (int) $f->muestras),
                    'l' => $hora($f->loopback_min, $f->loopback_avg, $f->loopback_max, (int) $f->muestras),
                    'pa' => $hora(null, $f->admin_perdida_avg, $f->admin_perdida_max, (int) $f->muestras),
                    'pl' => $hora(null, $f->loopback_perdida_avg, $f->loopback_perdida_max, (int) $f->muestras)]);
        }

        // Suma una fila (o un intervalo) a un acumulado
        $vacio = fn () => ['n' => 0, 'ok' => 0] + array_fill_keys(array_keys($metricas), [null, 0.0, null, 0]);
        $sumar = function (array &$acc, array $f) use ($metricas) {
            $acc['n'] += $f['n'];
            $acc['ok'] += $f['ok'];
            foreach (array_keys($metricas) as $q) {
                [$min, $suma, $max, $cuantas] = $f[$q];
                if ($cuantas) {
                    $acc[$q][0] = $acc[$q][0] === null ? $min : min($acc[$q][0], $min);
                    $acc[$q][1] += $suma;
                    $acc[$q][2] = $acc[$q][2] === null ? $max : max($acc[$q][2], $max);
                    $acc[$q][3] += $cuantas;
                }
            }
        };

        // Intervalos parejos de $paso segundos
        $cubos = [];
        foreach ($filas as $f) {
            $k = intdiv($f['t']->getTimestamp(), $paso) * $paso;
            $cubos[$k] ??= $vacio();
            $sumar($cubos[$k], $f);
        }

        $r1 = fn ($v) => $v === null ? null : round((float) $v, 1);
        $prom = fn (array $m) => $m[3] ? $r1($m[1] / $m[3]) : null;
        $puntos = [];
        $tot = $vacio();
        for ($k = $desde->getTimestamp(); $k <= $hasta->getTimestamp(); $k += $paso) {
            $c = $cubos[$k] ?? null;
            $lat = fn (string $q) => $c && $c[$q][3] ? ['min' => $r1($c[$q][0]), 'avg' => $prom($c[$q]), 'max' => $r1($c[$q][2])] : null;
            $per = fn (string $q) => $c && $c[$q][3] ? ['avg' => $prom($c[$q]), 'max' => $r1($c[$q][2])] : null;
            $puntos[] = ['t' => Carbon::createFromTimestamp($k, $hasta->getTimezone())->format('Y-m-d\TH:i'),
                'disp' => $c && $c['n'] ? round($c['ok'] * 100 / $c['n'], 1) : null,
                'admin' => $lat('a'), 'loopback' => $lat('l'),
                'perdida' => ['admin' => $per('pa'), 'loopback' => $per('pl')]];
            if ($c) {
                $sumar($tot, $c);
            }
        }

        $ultima = DB::table('recaudadora_mediciones')->where('recaudadora_id', $r->id)->orderByDesc('medido_en')->first();
        $resumen = fn (string $q, string $campo) => ['ultimo' => $ultima ? $r1($ultima->{$campo}) : null,
            'min' => $r1($tot[$q][0]), 'avg' => $prom($tot[$q]), 'max' => $r1($tot[$q][2])];
        $conPerdida = $tot['pa'][3] || $tot['pl'][3];

        return [
            'rango' => $rango,
            'intervalo_s' => $paso,
            'puntos' => $puntos,
            'resumen' => [
                'muestras' => $tot['n'],
                'disponibilidad' => $tot['n'] ? round($tot['ok'] * 100 / $tot['n'], 2) : null,
                'admin' => $r->ip_administrativa ? $resumen('a', 'admin_ms') : null,
                'loopback' => $r->ip_loopback ? $resumen('l', 'loopback_ms') : null,
                // Pérdida de paquetes (%); null mientras no haya revisiones con el dato
                'perdida' => $conPerdida ? [
                    'admin' => $r->ip_administrativa ? $resumen('pa', 'admin_perdida') : null,
                    'loopback' => $r->ip_loopback ? $resumen('pl', 'loopback_perdida') : null,
                ] : null,
                'desde_registro' => DB::table('recaudadora_mediciones_hora')->where('recaudadora_id', $r->id)->min('hora'),
            ],
        ];
    }
}
