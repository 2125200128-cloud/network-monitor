<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaDispositivo extends Model
{
    protected $table = 'notas_dispositivo';

    protected $fillable = ['dispositivo_id', 'user_id', 'titulo', 'contenido', 'es_secreto'];

    protected $casts = [
        'contenido' => 'encrypted',
        'es_secreto' => 'boolean',
    ];

    public function dispositivo()
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
