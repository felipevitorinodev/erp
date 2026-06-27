<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $empresa = Empresa::first();

        User::firstOrCreate(
            ['email' => 'admin@empresa.com'],
            [
                'empresa_id' => $empresa->id,
                'name' => 'Administrador',
                'password' => Hash::make('123456'),
            ]
        );
    }
}