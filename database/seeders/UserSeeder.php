<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@hacienda.gob.mx'],
            [
                'name' => 'Administrador NOC',
                'role' => 'Admin',
                // Contraseña inicial desde .env (SEED_ADMIN_PASSWORD); si falta se genera una aleatoria.
                'password' => Hash::make(env('SEED_ADMIN_PASSWORD') ?: tap(\Illuminate\Support\Str::password(16), fn ($p) => $this->command?->warn("Contraseña inicial del admin: {$p}"))),
            ]
        );
    }
}
