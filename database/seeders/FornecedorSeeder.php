<?php

namespace Database\Seeders;

use App\Models\Fornecedor;
use Illuminate\Database\Seeder;

class FornecedorSeeder extends Seeder
{
    public function run(): void
    {
        // Pessoa Jurídica
        Fornecedor::create([
            'empresa_id'          => 1,
            'nome'                => 'Distribuidora Teste LTDA',
            'nome_fantasia'       => 'Teste Distribuidora',
            'cnpj'                => '98.765.432/0001-11',
            'cpf'                 => null,
            'inscricao_estadual'  => '321.654.987.000',
            'inscricao_municipal' => '456789',
            'email'               => 'contato@distribuidorateste.com.br',
            'telefone'            => '(83) 3221-2222',
            'celular'             => '(83) 99999-2222',
            'cep'                 => '58000-100',
            'logradouro'          => 'Rua dos Fornecedores',
            'numero'              => '200',
            'complemento'         => 'Galpão 02',
            'bairro'              => 'Centro',
            'cidade'              => 'João Pessoa',
            'estado'              => 'PB',
            'observacoes'         => 'Fornecedor PJ de teste.',
        ]);

        // Pessoa Física
        Fornecedor::create([
            'empresa_id'          => 1,
            'nome'                => 'Maria Oliveira',
            'nome_fantasia'       => null,
            'cnpj'                => null,
            'cpf'                 => '987.654.321-00',
            'inscricao_estadual'  => null,
            'inscricao_municipal' => null,
            'email'               => 'maria.oliveira@email.com',
            'telefone'            => '(83) 3222-3333',
            'celular'             => '(83) 98888-3333',
            'cep'                 => '58020-200',
            'logradouro'          => 'Rua João Suassuna',
            'numero'              => '350',
            'complemento'         => 'Casa',
            'bairro'              => 'Bessa',
            'cidade'              => 'João Pessoa',
            'estado'              => 'PB',
            'observacoes'         => 'Fornecedor PF de teste.',
        ]);
    }
}