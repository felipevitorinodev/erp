<?php

namespace Database\Seeders;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
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

        if (!$empresa) {
            $this->command?->error('Nenhuma empresa encontrada. Rode EmpresaSeeder antes.');

            return;
        }

        User::firstOrCreate(
            ['email' => 'admin@dev.com'],
            [
                'empresa_id' => $empresa->id,
                'name'       => 'Desenvolvedor',
                'password'   => Hash::make('dev@123'),
                'perfil'     => 'admin',
                'ativo'      => true,
            ]
        );

        // Não altera senha/nome se o usuário já existir — só garante acesso e o vínculo
        User::where('email', 'admin@dev.com')->update([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo'  => true,
        ]);
    }
}
