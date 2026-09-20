<?php

namespace Database\Seeders;

use App\Models\UnidadeMedida;
use Illuminate\Database\Seeder;

class UnidadeMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [
            ['nome' => 'Unidade', 'sigla' => 'UN'],
            ['nome' => 'Caixa', 'sigla' => 'CX'],
            ['nome' => 'Quilograma', 'sigla' => 'KG'],
        ];

        foreach ($unidades as $unidade) {
            UnidadeMedida::firstOrCreate(
                [
                    'empresa_id' => 1,
                    'sigla' => $unidade['sigla'],
                ],
                [
                    'nome' => $unidade['nome'],
                    'ativo' => true,
                ]
            );
        }
    }
}
