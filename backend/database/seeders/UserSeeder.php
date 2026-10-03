<?php

namespace Database\Seeders;

use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Sin sede no se puede vender ni mover stock: arrancan en la primera.
        $sede = Sede::query()->orderBy('id')->value('id');

        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrador',
                'password' => env('ADMIN_PASSWORD', 'tharwah'),
                'active' => true,
                'sede_id' => $sede,
            ],
        );

        $prueba = User::firstOrCreate(
            ['email' => 'password@gmail.com'],
            [
                'name' => 'Usuario de Prueba',
                'username' => 'password',
                'password' => 'password',
                'active' => true,
                'sede_id' => $sede,
            ],
        );

        $admin->assignRole('Administrador');
        $prueba->assignRole('Administrador');
    }
}
