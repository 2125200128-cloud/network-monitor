<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditoriaComando extends Model
{
    use HasFactory;

    protected $table = 'auditoria_comandos';
    public $timestamps = false; // We are managing the timestamp manually as fecha_ejecucion

    protected $fillable = [
        'user_id',
        'dispositivo_id',
        'comando_solicitado',
        'ip_origen',
        'estado_ejecucion',
        'salida_terminal',
        'fecha_ejecucion',
    ];

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
