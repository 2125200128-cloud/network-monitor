<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionDispositivo extends Model
{
    protected $table = 'configuraciones_dispositivo';

    protected $fillable = [
        'dispositivo_id',
        'user_id',
        'tipo',
        'contenido',
        'checksum_sha256'
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
