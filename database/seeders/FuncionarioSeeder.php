<?php

namespace Database\Seeders;

use App\Models\Funcionario;
use Illuminate\Database\Seeder;

class FuncionarioSeeder extends Seeder
{
    public function run(): void
    {
        // Funcionário com acesso ao sistema
        Funcionario::create([
            'empresa_id'      => 1,
            'usuario_id'      => 1,
            'nome'            => 'Carlos Eduardo Santos',
            'cpf'             => '111.222.333-44',
            'rg'              => '1234567',
            'data_nascimento' => '1990-05-12',
            'email'           => 'carlos.santos@empresa.com.br',
            'telefone'        => '(83) 3222-4444',
            'celular'         => '(83) 98888-4444',
            'cargo'           => 'Analista Administrativo',
            'departamento'    => 'Administrativo',
            'salario'         => 3200.00,
            'data_admissao'   => '2022-01-10',
            'data_demissao'   => null,
            'tipo_contrato'   => 'CLT',
            'ativo'           => true,
            'observacoes'     => 'Funcionário de teste.',
        ]);

        // Funcionário sem acesso ao sistema
        Funcionario::create([
            'empresa_id'      => 1,
            'usuario_id'      => null,
            'nome'            => 'Fernanda Lima Costa',
            'cpf'             => '555.666.777-88',
            'rg'              => '7654321',
            'data_nascimento' => '1995-09-23',
            'email'           => 'fernanda.costa@empresa.com.br',
            'telefone'        => '(83) 3222-5555',
            'celular'         => '(83) 98888-5555',
            'cargo'           => 'Auxiliar de Estoque',
            'departamento'    => 'Logística',
            'salario'         => 1800.00,
            'data_admissao'   => '2023-03-01',
            'data_demissao'   => null,
            'tipo_contrato'   => 'CLT',
            'ativo'           => true,
            'observacoes'     => 'Funcionário de teste.',
        ]);
    }
}