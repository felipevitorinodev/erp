<?php

namespace Database\Seeders;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $empresa = Empresa::first();

        $email = env('SEED_ADMIN_EMAIL', 'admin@visys.app');
        $name = env('SEED_ADMIN_NAME', 'Administrador');
        $password = env('SEED_ADMIN_PASSWORD');

        if ($password === null || $password === '') {
            $password = Str::random(16);
            $this->command?->warn("Senha do administrador gerada (anote agora — não será exibida novamente): {$password}");
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'empresa_id' => $empresa->id,
                'name'       => $name,
                'password'   => Hash::make($password),
                'perfil'     => 'admin',
                'ativo'      => true,
            ]
        );

        // Não altera senha/nome se o usuário já existir — só garante acesso
        User::where('email', $email)->update([
            'perfil' => 'admin',
            'ativo'  => true,
        ]);
    }
}
