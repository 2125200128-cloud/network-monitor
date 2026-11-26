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
                (recaudadora_id, hora, muestras, ok_muestras, admin_min, admin_avg, admin_max, loopback_min, loopback_avg, loopback_max)
            SELECT recaudadora_id, date_trunc('hour', medido_en), count(*), count(*) FILTER (WHERE ok),
                   min(admin_ms), avg(admin_ms), max(admin_ms), min(loopback_ms), avg(loopback_ms), max(loopback_ms)
            FROM recaudadora_mediciones
            WHERE medido_en >= ?
            GROUP BY 1, 2
            ON CONFLICT (recaudadora_id, hora) DO UPDATE SET
                muestras = EXCLUDED.muestras, ok_muestras = EXCLUDED.ok_muestras,
                admin_min = EXCLUDED.admin_min, admin_avg = EXCLUDED.admin_avg, admin_max = EXCLUDED.admin_max,
                loopback_min = EXCLUDED.loopback_min, loopback_avg = EXCLUDED.loopback_avg, loopback_max = EXCLUDED.loopback_max
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

        // Cada fila, a la forma del resumen por hora: muestras, ok y mín/suma/máx de cada IP
        if ($fuente === 'detalle') {
            $filas = DB::table('recaudadora_mediciones')->where('recaudadora_id', $r->id)->where('medido_en', '>=', $desde)
                ->orderBy('medido_en')->get(['medido_en as t', 'ok', 'admin_ms', 'loopback_ms'])
                ->map(fn ($f) => ['t' => Carbon::parse($f->t), 'n' => 1, 'ok' => (int) $f->ok,
                    'a' => [$f->admin_ms, $f->admin_ms, $f->admin_ms, $f->admin_ms === null ? 0 : 1],
                    'l' => [$f->loopback_ms, $f->loopback_ms, $f->loopback_ms, $f->loopback_ms === null ? 0 : 1]]);
        } else {
            $filas = DB::table('recaudadora_mediciones_hora')->where('recaudadora_id', $r->id)->where('hora', '>=', $desde->copy()->startOfHour())
                ->orderBy('hora')->get()
                ->map(fn ($f) => ['t' => Carbon::parse($f->hora), 'n' => (int) $f->muestras, 'ok' => (int) $f->ok_muestras,
                    // el promedio de la hora pesa por sus muestras con respuesta (aprox.: las muestras de la hora)
                    'a' => [$f->admin_min, $f->admin_avg === null ? null : $f->admin_avg * $f->muestras, $f->admin_max, $f->admin_avg === null ? 0 : (int) $f->muestras],
                    'l' => [$f->loopback_min, $f->loopback_avg === null ? null : $f->loopback_avg * $f->muestras, $f->loopback_max, $f->loopback_avg === null ? 0 : (int) $f->muestras]]);
        }

        // Intervalos parejos de $paso segundos
        $cubos = [];
        foreach ($filas as $f) {
            $k = intdiv($f['t']->getTimestamp(), $paso) * $paso;
            $c = &$cubos[$k];
            $c ??= ['n' => 0, 'ok' => 0, 'a' => [null, 0.0, null, 0], 'l' => [null, 0.0, null, 0]];
            $c['n'] += $f['n'];
            $c['ok'] += $f['ok'];
            foreach (['a', 'l'] as $ip) {
                [$min, $suma, $max, $cuantas] = $f[$ip];
                if ($cuantas) {
                    $c[$ip][0] = $c[$ip][0] === null ? $min : min($c[$ip][0], $min);
                    $c[$ip][1] += $suma;
                    $c[$ip][2] = $c[$ip][2] === null ? $max : max($c[$ip][2], $max);
                    $c[$ip][3] += $cuantas;
                }
            }
            unset($c);
        }

        $puntos = [];
        $tot = ['n' => 0, 'ok' => 0, 'a' => [null, 0.0, null, 0], 'l' => [null, 0.0, null, 0]];
        $r1 = fn ($v) => $v === null ? null : round((float) $v, 1);
        for ($k = $desde->getTimestamp(); $k <= $hasta->getTimestamp(); $k += $paso) {
            $c = $cubos[$k] ?? null;
            $ip = fn (string $q) => $c && $c[$q][3] ? ['min' => $r1($c[$q][0]), 'avg' => $r1($c[$q][1] / $c[$q][3]), 'max' => $r1($c[$q][2])] : null;
            $puntos[] = ['t' => Carbon::createFromTimestamp($k, $hasta->getTimezone())->format('Y-m-d\TH:i'),
                'disp' => $c && $c['n'] ? round($c['ok'] * 100 / $c['n'], 1) : null, 'admin' => $ip('a'), 'loopback' => $ip('l')];
            if ($c) {
                $tot['n'] += $c['n'];
                $tot['ok'] += $c['ok'];
                foreach (['a', 'l'] as $q) {
                    if ($c[$q][3]) {
                        $tot[$q][0] = $tot[$q][0] === null ? $c[$q][0] : min($tot[$q][0], $c[$q][0]);
                        $tot[$q][1] += $c[$q][1];
                        $tot[$q][2] = $tot[$q][2] === null ? $c[$q][2] : max($tot[$q][2], $c[$q][2]);
                        $tot[$q][3] += $c[$q][3];
                    }
                }
            }
        }

        $ultima = DB::table('recaudadora_mediciones')->where('recaudadora_id', $r->id)->orderByDesc('medido_en')->first();
        $resumenIp = fn (string $q, string $campo) => [
            'ultimo' => $ultima ? $r1($ultima->{$campo}) : null,
            'min' => $r1($tot[$q][0]), 'avg' => $tot[$q][3] ? $r1($tot[$q][1] / $tot[$q][3]) : null, 'max' => $r1($tot[$q][2]),
        ];

        return [
            'rango' => $rango,
            'intervalo_s' => $paso,
            'puntos' => $puntos,
            'resumen' => [
                'muestras' => $tot['n'],
                'disponibilidad' => $tot['n'] ? round($tot['ok'] * 100 / $tot['n'], 2) : null,
                'admin' => $r->ip_administrativa ? $resumenIp('a', 'admin_ms') : null,
                'loopback' => $r->ip_loopback ? $resumenIp('l', 'loopback_ms') : null,
                'desde_registro' => DB::table('recaudadora_mediciones_hora')->where('recaudadora_id', $r->id)->min('hora'),
            ],
        ];
    }
}
