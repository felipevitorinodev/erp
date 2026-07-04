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
            'razao_social' => 'Desenvolvimento',
            'nome_fantasia' => 'Desenvolvimento',
            'tipo_pessoa' => TipoPessoa::PJ->value,
            'cnpj' => '12.345.678/0001-99',
            'ie' => '123456789',
            'im' => '987654321',
            'email' => 'admin@dev.com',
            'telefone' => '(83) 3333-3333',
            'celular' => '(83) 99999-9999',
            'cep' => '58000-000',
            'logradouro' => 'Desenvolvimento',
            'numero' => '00',
            'complemento' => null,
            'bairro' => 'Centro',
            'cidade' => 'João Pessoa',
            'uf' => 'PB',
            'ativo' => 1,
        ]);
    }
}