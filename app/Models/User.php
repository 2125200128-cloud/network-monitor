<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'prefijo',
        'avatar',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Retorna la URL completa del avatar del usuario o un fallback con iniciales
     */
    public function getAvatarUrlAttribute(): string
    {
        if (!empty($this->avatar) && file_exists(public_path('storage/' . $this->avatar))) {
            return asset('storage/' . $this->avatar);
        }

        $bg = strtolower($this->role ?? '') === 'admin' ? '3b5998' : (strtolower($this->role ?? '') === 'operador' ? '16a34a' : 'f26419');
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name ?? 'User') . '&background=' . $bg . '&color=fff&size=128';
    }

    /**
     * Posiciones personalizadas del mapa de topología de red guardadas por la cuenta
     */
    public function posicionTopologia()
    {
        return $this->hasOne(PosicionTopologia::class);
    }
}
