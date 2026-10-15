<?php

namespace App\Console\Commands;

use App\Models\AlarmaRed;
use App\Services\AlarmDiagnosisService;
use App\Services\SslCertificateChecker;
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
 */
class NocSyncCommand extends Command
{
    protected $signature = 'noc:sync';

    protected $description = 'Registra el historial de alarmas y consolida métricas por hora para el dashboard.';

    public function handle(AlarmDiagnosisService $diagnostico, SslCertificateChecker $ssl): int
    {
        $certificados = $ssl->revisarPendientes();
        [$nuevas, $resueltas] = $this->registrarAlarmas($diagnostico);
        $horas = $this->consolidarMetricas();
        Cache::forever('noc_sync_ultimo', now()->toDateTimeString());
        $this->info("SSL: {$certificados} revisados. Alarmas: {$nuevas} nuevas, {$resueltas} resueltas. "
            . "Consolidado: {$horas} filas equipo/hora.");

        return Command::SUCCESS;
    }

    private function registrarAlarmas(AlarmDiagnosisService $diagnostico): array
    {
        // Las informativas (respaldos NCM) no son incidentes
        $alarmas = collect($diagnostico->diagnosticarAlarmasRed(false))
            ->reject(fn ($a) => ($a['severidad'] ?? null) === 'informativa')
            ->keyBy('id');

        $abiertas = AlarmaRed::whereNotNull('clave')->where('estado', '!=', 'resuelta')->get()->keyBy('clave');
        $nuevas = 0;

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

            AlarmaRed::create([
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
            $nuevas++;
        }

        $resueltas = AlarmaRed::whereNotNull('clave')
            ->where('estado', '!=', 'resuelta')
            ->whereNotIn('clave', $alarmas->keys()->all())
            ->update(['estado' => 'resuelta', 'fecha_resolucion' => now(), 'updated_at' => now()]);

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
            $filas += DB::affectingStatement("
                INSERT INTO metricas_red_hora
                    (dispositivo_id, hora, muestras, muestras_up, ping_avg, ping_std, ping_max, loss_avg, cpu_avg, cpu_max, mem_avg)
                SELECT dispositivo_id,
                       DATE_FORMAT(fecha_registro, '%Y-%m-%d %H:00:00') AS h,
                       COUNT(*),
                       SUM(packet_loss IS NULL OR packet_loss < 100),
                       ROUND(AVG(ping_ms), 2), ROUND(STDDEV_POP(ping_ms), 2), MAX(ping_ms),
                       ROUND(AVG(packet_loss), 2), ROUND(AVG(cpu_usage), 2), MAX(cpu_usage), ROUND(AVG(memory_usage), 2)
                FROM metricas_red
                WHERE fecha_registro >= ? AND fecha_registro < ?
                GROUP BY dispositivo_id, h
                ON DUPLICATE KEY UPDATE muestras = VALUES(muestras), muestras_up = VALUES(muestras_up),
                    ping_avg = VALUES(ping_avg), ping_std = VALUES(ping_std), ping_max = VALUES(ping_max),
                    loss_avg = VALUES(loss_avg), cpu_avg = VALUES(cpu_avg), cpu_max = VALUES(cpu_max),
                    mem_avg = VALUES(mem_avg)
            ", [$ini->toDateTimeString(), $hasta->toDateTimeString()]);
        }

        return $filas;
    }
}
