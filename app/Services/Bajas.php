<?php

namespace App\Services;

use App\Models\AlarmaRed;
use App\Models\Dispositivo;
use App\Models\Recaudadora;
use App\Models\RecaudadoraEquipo;
use App\Models\RecaudadoraEvento;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Equipos y recaudadoras dados de baja desde su ficha: dejan de generar alertas y avisos de Telegram y correo.
 *  - Equipo: el diagnóstico deja fuera sus alarmas (por equipo o por su IP). El sondeo lo sigue leyendo, así que al
 *    reactivarlo su estado está al día.
 *  - Recaudadora: deja de revisarse (activo = false, como «Monitorear» en sus datos) y se cierran sus caídas abiertas.
 * Sus alertas abiertas se cierran aquí mismo, sin pasar por noc:sync: así no sale un aviso de «resuelta».
 */
class Bajas
{
    /** IDs e IPs de los equipos dados de baja, para que el diagnóstico deje fuera sus alarmas. */
    public static function equipos(): array
    {
        return Cache::remember('dispositivos_baja_v1', 30, function () {
            $d = Dispositivo::whereNotNull('baja_at')->get(['id', 'ip']);
            return ['ids' => $d->pluck('id')->all(), 'ips' => $d->pluck('ip')->filter()->values()->all()];
        });
    }

    public static function darDeBajaEquipo(Dispositivo $d, ?User $user, ?string $motivo): int
    {
        return DB::transaction(function () use ($d, $user, $motivo) {
            $d->forceFill(['baja_at' => now(), 'baja_por' => self::quien($user), 'baja_motivo' => $motivo ?: null])->save();
            $cerradas = self::cerrarAlarmas(AlarmaRed::where(fn ($q) => $q->where('dispositivo_id', $d->id)
                ->when($d->ip, fn ($q) => $q->orWhere('ip', $d->ip))));
            self::olvidar();
            Log::info('Equipo dado de baja', ['dispositivo' => "{$d->id} {$d->nombre} ({$d->ip})", 'usuario' => $user?->usuario, 'motivo' => $motivo, 'alertas_cerradas' => $cerradas]);
            return $cerradas;
        });
    }

    public static function reactivarEquipo(Dispositivo $d, ?User $user): void
    {
        $d->forceFill(['baja_at' => null, 'baja_por' => null, 'baja_motivo' => null])->save();
        self::olvidar();
        Log::info('Equipo reactivado', ['dispositivo' => "{$d->id} {$d->nombre} ({$d->ip})", 'usuario' => $user?->usuario]);
    }

    public static function darDeBajaRecaudadora(Recaudadora $r, ?User $user, ?string $motivo): int
    {
        return DB::transaction(function () use ($r, $user, $motivo) {
            $r->forceFill(['activo' => false, 'baja_at' => now(), 'baja_por' => self::quien($user), 'baja_motivo' => $motivo ?: null])->save();
            // Las caídas abiertas terminan aquí: desde ahora no se mide (no cuenta como tiempo sin comunicación)
            RecaudadoraEvento::where('recaudadora_id', $r->id)->whereNull('fin')->update(['fin' => now()]);
            $claves = RecaudadoraEquipo::where('recaudadora_id', $r->id)->pluck('id')->map(fn ($id) => "reca-equipo-{$id}")
                ->push("reca-{$r->id}-sin-comunicacion", "reca-{$r->id}-red-interna")->all();
            $cerradas = self::cerrarAlarmas(AlarmaRed::whereIn('clave', $claves));
            self::olvidar();
            Log::info('Recaudadora dada de baja', ['recaudadora' => $r->titulo(), 'usuario' => $user?->usuario, 'motivo' => $motivo, 'alertas_cerradas' => $cerradas]);
            return $cerradas;
        });
    }

    /** Vuelve a revisarse; la primera revisión solo toma su estado, sin avisar (como una recaudadora nueva). */
    public static function reactivarRecaudadora(Recaudadora $r, ?User $user): void
    {
        DB::transaction(function () use ($r) {
            $r->forceFill(['activo' => true, 'baja_at' => null, 'baja_por' => null, 'baja_motivo' => null,
                'estado' => null, 'estado_desde' => null, 'fallos_seguidos' => 0])->save();
            RecaudadoraEquipo::where('recaudadora_id', $r->id)->update(['estado' => null, 'estado_desde' => null, 'fallos_seguidos' => 0]);
        });
        self::olvidar();
        Log::info('Recaudadora reactivada', ['recaudadora' => $r->titulo(), 'usuario' => $user?->usuario]);
    }

    private static function cerrarAlarmas($consulta): int
    {
        return $consulta->where('estado', '!=', 'resuelta')->update(['estado' => 'resuelta', 'fecha_resolucion' => now(), 'updated_at' => now()]);
    }

    private static function quien(?User $user): ?string
    {
        return $user ? trim(($user->prefijo ? $user->prefijo . ' ' : '') . $user->name) : null;
    }

    /** Que el diagnóstico compartido y la lista de bajas se recalculen ya. */
    private static function olvidar(): void
    {
        Cache::forget('dispositivos_baja_v1');
        Cache::forget('diagnostico_alarmas_red_fast');
    }
}
