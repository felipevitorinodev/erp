<?php

namespace Database\Seeders;

use App\Enums\TipoPessoa;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class EmpresaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Empresa::create([
            'razao_social' => 'Empresa Exemplo LTDA',
            'nome_fantasia' => 'ERP Exemplo',
            'tipo_pessoa' => TipoPessoa::PJ->value,
            'cnpj' => '12.345.678/0001-99',
            'ie' => '123456789',
            'im' => '987654321',
            'email' => 'contato@empresa.com',
            'telefone' => '(83) 3333-3333',
            'celular' => '(83) 99999-9999',
            'cep' => '58000-000',
            'logradouro' => 'Rua das Flores',
            'numero' => '100',
            'complemento' => null,
            'bairro' => 'Centro',
            'cidade' => 'João Pessoa',
            'uf' => 'PB',
            'ativo' => 1,
        ]);
    }
}