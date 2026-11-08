<?php

namespace Tests\Unit;

use App\Models\FormasPagamento\FormaPagamento;
use Tests\TestCase;

class FormaPagamentoTest extends TestCase
{
    public function test_cartao_de_debito_nao_parcela_mesmo_marcado_a_prazo(): void
    {
        $debito = new FormaPagamento([
            'nome' => 'Cartão de Débito',
            'tipo' => 'prazo',
            'gera_conta_receber' => true,
        ]);

        $this->assertTrue($debito->ehCartao());
        $this->assertTrue($debito->ehCartaoDebito());
        $this->assertFalse($debito->permiteParcelar());
    }

    public function test_cartao_de_credito_continua_parcelavel(): void
    {
        $credito = new FormaPagamento([
            'nome' => 'Cartão de Crédito',
            'tipo' => 'avista',
            'gera_conta_receber' => false,
        ]);

        $this->assertFalse($credito->ehCartaoDebito());
        $this->assertTrue($credito->permiteParcelar());
    }
}
