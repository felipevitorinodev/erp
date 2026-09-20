<?php

namespace Database\Seeders;

use App\Models\Grupo;
use Illuminate\Database\Seeder;

class GrupoSeeder extends Seeder
{
    public function run(): void
    {
        Grupo::firstOrCreate(
            [
                'empresa_id' => 1,
                'nome' => 'SELECIONE',
            ],
            [
                'parent_id' => null,
                'descricao' => '',
                'ativo' => true,
            ]
        );
    }
}
