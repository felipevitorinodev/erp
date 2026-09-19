<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\FormaPagamento;
use Illuminate\Database\Seeder;

class FormaPagamentoSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            return;
        }

        $formas = [
            // À vista — não gera conta a receber
            ['nome' => 'Dinheiro',              'tipo' => 'avista', 'gera_conta_receber' => false, 'dias_vencimento' => 0],
            ['nome' => 'Pix',                   'tipo' => 'avista', 'gera_conta_receber' => false, 'dias_vencimento' => 0],
            ['nome' => 'Cartão de Débito',      'tipo' => 'avista', 'gera_conta_receber' => false, 'dias_vencimento' => 0],
            ['nome' => 'Cartão de Crédito',     'tipo' => 'avista', 'gera_conta_receber' => false, 'dias_vencimento' => 0],
            ['nome' => 'Transferência',         'tipo' => 'avista', 'gera_conta_receber' => false, 'dias_vencimento' => 0],

            // A prazo — gera conta a receber automaticamente
            ['nome' => 'Duplicata',             'tipo' => 'prazo',  'gera_conta_receber' => true,  'dias_vencimento' => 30],
            ['nome' => 'Boleto',                'tipo' => 'prazo',  'gera_conta_receber' => true,  'dias_vencimento' => 30],
            ['nome' => 'Carnê',                 'tipo' => 'prazo',  'gera_conta_receber' => true,  'dias_vencimento' => 30],
            ['nome' => 'Cheque',                'tipo' => 'prazo',  'gera_conta_receber' => true,  'dias_vencimento' => 30],
            ['nome' => 'A Prazo',               'tipo' => 'prazo',  'gera_conta_receber' => true,  'dias_vencimento' => 30],
            ['nome' => 'Crediário',             'tipo' => 'prazo',  'gera_conta_receber' => true,  'dias_vencimento' => 30],
        ];

        foreach ($formas as $forma) {
            FormaPagamento::firstOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'nome'       => $forma['nome'],
                ],
                [
                    'tipo'               => $forma['tipo'],
                    'gera_conta_receber' => $forma['gera_conta_receber'],
                    'dias_vencimento'    => $forma['dias_vencimento'],
                    'ativo'              => true,
                ]
            );
        }
    }
}
