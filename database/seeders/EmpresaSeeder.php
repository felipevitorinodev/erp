<?php

namespace Database\Seeders;

use App\Enums\TipoPessoa;
use App\Models\Empresas\Empresa;
use Illuminate\Database\Seeder;

class EmpresaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cnpj = env('SEED_EMPRESA_CNPJ', '00.000.000/0001-00');

        Empresa::firstOrCreate(
            ['cnpj' => $cnpj],
            [
                'razao_social' => env('SEED_EMPRESA_RAZAO', 'Minha Empresa'),
                'nome_fantasia' => env('SEED_EMPRESA_FANTASIA', 'Minha Empresa'),
                'tipo_pessoa' => TipoPessoa::PJ->value,
                'ie' => null,
                'im' => null,
                'email' => env('SEED_EMPRESA_EMAIL', 'contato@minhaempresa.com'),
                'telefone' => null,
                'celular' => null,
                'cep' => null,
                'logradouro' => null,
                'numero' => null,
                'complemento' => null,
                'bairro' => null,
                'cidade' => env('SEED_EMPRESA_CIDADE', 'São Paulo'),
                'uf' => env('SEED_EMPRESA_UF', 'SP'),
                'ativo' => 1,
            ]
        );
    }
}
