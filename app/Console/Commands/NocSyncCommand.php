<?php

namespace App\Console\Commands;

use App\Models\AlarmaRed;
use App\Models\ConfiguracionGeneral;
use App\Services\AlarmDiagnosisService;
use App\Services\AlertasTelegram;
use App\Services\SslCertificateChecker;
use App\Support\Sql;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tareas periódicas del NOC (cada 30 s desde iniciar_monitor.bat):
 *  1. Certificados SSL de los servicios web (cada servicio cada 6 h).
 *  2. Historial de alarmas: registra en alarmas_red cada alarma nueva del diagnóstico y marca
 *     como resueltas las que dejaron de presentarse. De ahí salen incidentes, MTTR y MTBF.
 *  3. Consolidado horario: resume metricas_red por equipo y hora en metricas_red_hora
 *     (disponibilidad, latencia, jitter, CPU) para consultas de 24 h / 7 d / 30 d.
 *     Las nuevas y resueltas se avisan por Telegram si está activo (Configuración → Notificaciones).
 *  4. Retención (máximo una vez por hora): borra lo más antiguo que los días de Configuración
 *     (o config/noc.php si están vacíos).
 */
class NocSyncCommand extends Command
{
    protected $signature = 'noc:sync';

    protected $description = 'Registra el historial de alarmas y consolida métricas por hora para el dashboard.';

    public function handle(AlarmDiagnosisService $diagnostico, SslCertificateChecker $ssl, AlertasTelegram $telegram): int
    {
        $certificados = $ssl->revisarPendientes();
        [$nuevas, $resueltas] = $this->registrarAlarmas($diagnostico);
        $avisos = $telegram->notificar($nuevas, $resueltas);
        $horas = $this->consolidarMetricas();
        $borradas = Cache::add('noc_retencion_hecha', true, now()->addHour()) ? $this->aplicarRetencion() : null;
        Cache::forever('noc_sync_ultimo', now()->toDateTimeString());
        $this->info("SSL: {$certificados} revisados. Alarmas: {$nuevas->count()} nuevas, {$resueltas->count()} resueltas. "
            . ($avisos['enviados'] || $avisos['errores'] ? "Telegram: {$avisos['enviados']} enviados" . ($avisos['errores'] ? ", {$avisos['errores']} con error" : '') . '. ' : '')
            . "Consolidado: {$horas} filas equipo/hora."
            . ($borradas === null ? '' : ' Retención: ' . ($borradas ? collect($borradas)->map(fn ($n, $t) => "{$t} -{$n}")->implode(', ') : 'nada que borrar') . '.'));

        return Command::SUCCESS;
    }

    private function registrarAlarmas(AlarmDiagnosisService $diagnostico): array
    {
        // Las informativas (respaldos NCM) no son incidentes
        $alarmas = collect($diagnostico->diagnosticarAlarmasRed(false))
            ->reject(fn ($a) => ($a['severidad'] ?? null) === 'informativa')
            ->keyBy('id');

        $abiertas = AlarmaRed::whereNotNull('clave')->where('estado', '!=', 'resuelta')->get()->keyBy('clave');
        $nuevas = collect();

        foreach ($alarmas as $clave => $a) {
            $severidad = in_array($a['severidad'], ['critica', 'advertencia'], true) ? $a['severidad'] : 'advertencia';
            if ($abierta = $abiertas->get($clave)) {
                $abierta->update(['severidad' => $severidad, 'titulo' => Str::limit($a['titulo'], 250, ''), 'mensaje' => $a['mensaje']]);
                continue;
            }

            // Inicio real del evento cuando el diagnóstico lo conoce (p.ej. última vez visto del equipo caído)
            try {
                $inicio = Carbon::createFromFormat('d/m/Y H:i:s', $a['fecha_falla']);
            } catch (\Throwable) {
                $inicio = null;
            }
            if (!$inicio || $inicio->isFuture()) {
                $inicio = now();
            }

            $nuevas[] = AlarmaRed::create([
                'clave' => $clave,
                'dispositivo_id' => $a['dispositivo_id'] ?? null,
                'interfaz_id' => $a['interfaz_id'] ?? null,
                'enlace_id' => $a['enlace_id'] ?? null,
                'codigo_evento' => $a['codigo_evento'],
                'severidad' => $severidad,
                'categoria' => $a['categoria'] ?? 'dispositivo',
                'titulo' => Str::limit($a['titulo'], 250, ''),
                'mensaje' => $a['mensaje'],
                'causa_raiz' => $a['causa_raiz'] ?? null,
                'impacto' => isset($a['impacto']) ? Str::limit($a['impacto'], 250, '') : null,
                'accion_sugerida' => $a['accion_sugerida'] ?? null,
                'estado' => 'activa',
                'fecha_inicio' => $inicio,
            ]);
        }

        // Las abiertas que ya no aparecen en el diagnóstico se resuelven
        $resueltas = $abiertas->reject(fn ($a, $clave) => $alarmas->has($clave))->values();
        if ($resueltas->isNotEmpty()) {
            $ahora = now();
            AlarmaRed::whereIn('id', $resueltas->pluck('id'))->where('estado', '!=', 'resuelta')
                ->update(['estado' => 'resuelta', 'fecha_resolucion' => $ahora, 'updated_at' => $ahora]);
            $resueltas->each(fn ($a) => $a->forceFill(['estado' => 'resuelta', 'fecha_resolucion' => $ahora]));
        }

        return [$nuevas, $resueltas];
    }

    /**
     * Recalcula la hora anterior y la actual (y, la primera vez, todo el historial disponible).
     * Una muestra cuenta como "arriba" si el equipo respondió ICMP o SNMP (packet_loss < 100 o NULL).
     */
    private function consolidarMetricas(): int
    {
        $ultima = DB::table('metricas_red_hora')->max('hora');
        $primera = $ultima ? Carbon::parse($ultima)->subHour() : DB::table('metricas_red')->min('fecha_registro');
        if (!$primera) {
            return 0;
        }

        $filas = 0;
        $fin = now()->addMinute();
        // Bloques de un día para no mantener un SELECT largo sobre metricas_red
        for ($ini = Carbon::parse($primera)->startOfHour(); $ini < $fin; $ini = $ini->copy()->addDay()) {
            $hasta = $ini->copy()->addDay()->min($fin);
            $hora = Sql::inicioDeHora('fecha_registro');
            $columnas = implode(', ', [
                'dispositivo_id', "{$hora} AS h", 'COUNT(*)', Sql::contarSi('packet_loss IS NULL OR packet_loss < 100'),
                Sql::redondear('AVG(ping_ms)', 2), Sql::redondear('STDDEV_POP(ping_ms)', 2), 'MAX(ping_ms)',
                Sql::redondear('AVG(packet_loss)', 2), Sql::redondear('AVG(cpu_usage)', 2), 'MAX(cpu_usage)',
                Sql::redondear('AVG(memory_usage)', 2),
            ]);
            $alDuplicar = Sql::alDuplicar(['dispositivo_id', 'hora'],
                ['muestras', 'muestras_up', 'ping_avg', 'ping_std', 'ping_max', 'loss_avg', 'cpu_avg', 'cpu_max', 'mem_avg']);
            $filas += DB::affectingStatement("
                INSERT INTO metricas_red_hora
                    (dispositivo_id, hora, muestras, muestras_up, ping_avg, ping_std, ping_max, loss_avg, cpu_avg, cpu_max, mem_avg)
                SELECT {$columnas}
                FROM metricas_red
                WHERE fecha_registro >= ? AND fecha_registro < ?
                GROUP BY dispositivo_id, {$hora}
                {$alDuplicar}
            ", [$ini->toDateTimeString(), $hasta->toDateTimeString()]);
        }

        return $filas;
    }

    /** Borra el histórico vencido de cada tabla. Devuelve [tabla => filas borradas] (solo las que borraron algo). */
    private function aplicarRetencion(): array
    {
        $dias = ConfiguracionGeneral::retencionDias();
        $tablas = [
            // tabla, columna de fecha, días, condición extra
            ['metricas_red', 'fecha_registro', $dias['metricas_red'], null],
            ['global_metrics', 'recorded_at', $dias['global_metrics'], null],
            ['metricas_red_hora', 'hora', $dias['metricas_red_hora'], null],
            ['alarmas_red', 'fecha_resolucion', $dias['alarmas_resueltas'], ['estado', 'resuelta']],
        ];

        $borradas = [];
        foreach ($tablas as [$tabla, $columna, $diasTabla, $extra]) {
            // Mínimo 1 día: el consolidado horario vuelve a leer las 2 últimas horas de metricas_red
            $limite = now()->subDays(max(1, (int) $diasTabla));
            $n = $this->borrarAntesDe($tabla, $columna, $limite, $extra);
            if ($n) {
                $borradas[$tabla] = $n;
            }
        }

        return $borradas;
    }

    /**
     * Borra por rangos de id (bloques cortos) para no bloquear la tabla
     * mientras los workers siguen escribiendo.
     */
    private function borrarAntesDe(string $tabla, string $columna, Carbon $limite, ?array $extra, int $bloque = 20000): int
    {
        $vencidas = fn () => DB::table($tabla)->where($columna, '<', $limite)
            ->when($extra, fn ($q) => $q->where($extra[0], $extra[1]));

        $rango = $vencidas()->selectRaw('MIN(id) AS desde, MAX(id) AS hasta')->first();
        if (!$rango || $rango->desde === null) {
            return 0;
        }

        $total = 0;
        for ($id = (int) $rango->desde; $id <= (int) $rango->hasta; $id += $bloque) {
            $total += $vencidas()->whereBetween('id', [$id, $id + $bloque - 1])->delete();
        }

        return $total;
    }
}
