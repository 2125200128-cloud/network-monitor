<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosicionTopologia extends Model
{
    use HasFactory;

    protected $table = 'posiciones_topologia';

    protected $fillable = [
        'user_id',
        'posiciones',
    ];

    protected $casts = [
        'posiciones' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
