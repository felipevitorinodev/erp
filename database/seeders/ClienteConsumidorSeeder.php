<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class ClienteConsumidorSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = Empresa::query()->get();

        if ($empresas->isEmpty()) {
            return;
        }

        foreach ($empresas as $empresa) {
            Cliente::firstOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'nome'       => 'Consumidor',
                ],
                [
                    'nome_fantasia'       => 'Consumidor',
                    'cpf'                 => null,
                    'cnpj'                => null,
                    'inscricao_estadual'  => null,
                    'inscricao_municipal' => null,
                    'email'               => null,
                    'telefone'            => null,
                    'celular'             => null,
                    'cep'                 => null,
                    'logradouro'          => null,
                    'numero'              => null,
                    'complemento'         => null,
                    'bairro'              => null,
                    'cidade'              => null,
                    'estado'              => null,
                    'observacoes'         => 'Cliente padrão para vendas sem identificação.',
                    'ativo'               => true,
                ]
            );
        }
    }
}
