<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificacionLeida extends Model
{
    use HasFactory;

    protected $table = 'notificaciones_leidas';

    protected $fillable = [
        'user_id',
        'notificacion_id',
        'estado',
        'fecha_evento',
    ];

    protected $casts = [
        'fecha_evento' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
