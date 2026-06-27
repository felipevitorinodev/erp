<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        // Pessoa Jurídica
        Cliente::create([
            'empresa_id'          => 1,
            'nome'                => 'Empresa Teste LTDA',
            'nome_fantasia'       => 'Teste Comércio',
            'cnpj'                => '12.345.678/0001-99',
            'cpf'                 => null,
            'inscricao_estadual'  => '123.456.789.000',
            'inscricao_municipal' => '987654',
            'email'               => 'contato@empresateste.com.br',
            'telefone'            => '(83) 3221-0000',
            'celular'             => '(83) 99999-0000',
            'cep'                 => '58000-000',
            'logradouro'          => 'Rua das Flores',
            'numero'              => '100',
            'complemento'         => 'Sala 01',
            'bairro'              => 'Centro',
            'cidade'              => 'João Pessoa',
            'estado'              => 'PB',
            'observacoes'         => 'Cliente PJ de teste.',
        ]);

        // Pessoa Física
        Cliente::create([
            'empresa_id'          => 1,
            'nome'                => 'João da Silva',
            'nome_fantasia'       => null,
            'cnpj'                => null,
            'cpf'                 => '123.456.789-00',
            'inscricao_estadual'  => null,
            'inscricao_municipal' => null,
            'email'               => 'joao.silva@email.com',
            'telefone'            => '(83) 3222-1111',
            'celular'             => '(83) 98888-1111',
            'cep'                 => '58020-000',
            'logradouro'          => 'Avenida Epitácio Pessoa',
            'numero'              => '500',
            'complemento'         => 'Apto 12',
            'bairro'              => 'Tambaú',
            'cidade'              => 'João Pessoa',
            'estado'              => 'PB',
            'observacoes'         => 'Cliente PF de teste.',
        ]);
    }
}