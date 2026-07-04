<?php

namespace Database\Seeders;

use App\Models\Grupo;
use Illuminate\Database\Seeder;

class GrupoSeeder extends Seeder
{
    public function run(): void
    {
        Grupo::create([
            'empresa_id' => 1,
            'parent_id'  => null,
            'nome'       => 'SELECIONE',
            'descricao'  => '',
            'ativo'      => true,
        ]);
    }
}