<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Equipo dentro de una recaudadora (antena, kiosco, switch…), revisado por ping cada minuto. */
class RecaudadoraEquipo extends Model
{
    protected $table = 'recaudadora_equipos';

    protected $guarded = ['id'];

    protected $casts = [
        'estado_desde' => 'datetime',
        'ultimo_chequeo' => 'datetime',
        'latencia_ms' => 'float',
        'alguna_vez' => 'boolean',
    ];

    public const TIPOS = [
        'antena' => 'Antena / radioenlace',
        'kiosco' => 'Kiosco',
        'terminal' => 'Terminal de pago',
        'switch' => 'Switch',
        'ap' => 'Access point',
        'pc' => 'PC de caja',
        'impresora' => 'Impresora',
        'modem' => 'Módem del proveedor',
        'otro' => 'Otro',
    ];

    public function recaudadora(): BelongsTo
    {
        return $this->belongsTo(Recaudadora::class);
    }

    protected static function booted(): void
    {
        // Una antena de recaudadora que también está en el sondeo y quedó como PC o sin identificar: se corrige su tipo
        // (solo al darla de alta o cambiarle IP/tipo, no en cada revisión del monitoreo)
        static::saved(fn (self $e) => $e->tipo === 'antena' && ($e->wasRecentlyCreated || $e->wasChanged(['ip', 'tipo']))
            ? self::marcarAntenaEnSondeo($e->ip) : null);
    }

    /** Pone tipo «antena» al equipo del sondeo con esa IP si nadie le fijó tipo y el clasificador lo tomó por PC o no lo identificó. */
    public static function marcarAntenaEnSondeo(?string $ip): int
    {
        return $ip ? Dispositivo::where('ip', $ip)->whereNull('tipo_manual')
            ->where(fn ($q) => $q->whereNull('tipo_detectado')->orWhereIn('tipo_detectado', ['pc', 'desconocido']))
            ->update(['tipo_manual' => 'antena']) : 0;
    }

    /** pendiente · online · offline · nunca (no ha respondido ni una vez: IP equivocada, apagado o bloquea el ping). */
    public function estadoVisible(): string
    {
        if (!$this->estado || !$this->ultimo_chequeo || $this->ultimo_chequeo->lt(now()->subMinutes(5))) {
            return 'pendiente';
        }
        return $this->estado === 'offline' && !$this->alguna_vez ? 'nunca' : $this->estado;
    }
}
