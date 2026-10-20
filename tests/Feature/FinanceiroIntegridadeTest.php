<?php

namespace Tests\Feature;

use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Repositories\ContasPagar\ContaPagarRepository;
use App\Repositories\ContasReceber\ContaReceberRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceiroIntegridadeTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['razao_social' => 'Empresa A', 'cnpj' => '11222333000181']);
        $this->actingAs(User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]));
    }

    private function contaReceber(array $dados = []): ContaReceber
    {
        return ContaReceber::create(array_merge([
            'empresa_id' => $this->empresa->id,
            'descricao' => 'Conta',
            'valor' => 100,
            'valor_pago' => 0,
            'data_vencimento' => now()->toDateString(),
            'situacao' => 'aberta',
        ], $dados));
    }

    public function test_baixa_em_conta_cancelada_e_recusada(): void
    {
        $conta = $this->contaReceber();
        $carregadaAntes = $conta->fresh();
        $repositorio = app(ContaReceberRepository::class);

        $repositorio->cancelar($conta);

        $this->expectException(\RuntimeException::class);
        $repositorio->receber($carregadaAntes, 50, now()->toDateString(), null);
    }

    public function test_cancelar_conta_que_recebeu_baixa_concorrente_e_recusado(): void
    {
        $conta = $this->contaReceber();
        $carregadaAntes = $conta->fresh();
        $repositorio = app(ContaReceberRepository::class);

        $repositorio->receber($conta, 30, now()->toDateString(), null);

        try {
            $repositorio->cancelar($carregadaAntes);
        } catch (\RuntimeException) {
        }

        $this->assertSame('parcial', $conta->fresh()->situacao);
    }

    public function test_pagar_conta_cancelada_e_recusado(): void
    {
        $conta = ContaPagar::create([
            'empresa_id' => $this->empresa->id,
            'descricao' => 'Conta',
            'valor' => 100,
            'valor_pago' => 0,
            'data_vencimento' => now()->toDateString(),
            'situacao' => 'aberta',
        ]);
        $carregadaAntes = $conta->fresh();
        $repositorio = app(ContaPagarRepository::class);

        $repositorio->cancelar($conta);

        $this->expectException(\RuntimeException::class);
        $repositorio->pagar($carregadaAntes, 50, now()->toDateString(), null);
    }

    public function test_editar_conta_nao_remove_vinculo_com_a_venda(): void
    {
        $venda = Venda::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'situacao' => 'confirmada',
            'total' => 100,
        ]);
        $conta = $this->contaReceber(['venda_id' => $venda->id]);

        $this->put(route('conta-receber.update', $conta), [
            'descricao' => 'Conta editada',
            'valor' => '100,00',
            'data_vencimento' => now()->toDateString(),
        ]);

        $this->assertSame($venda->id, $conta->fresh()->venda_id);
        $this->assertSame('Conta editada', $conta->fresh()->descricao);
    }

    public function test_baixa_com_data_futura_e_recusada(): void
    {
        $conta = $this->contaReceber();

        $this->post(route('conta-receber.receber', $conta), [
            'valor_pago' => '10,00',
            'data_pagamento' => now()->addDays(3)->toDateString(),
        ])->assertSessionHasErrors('data_pagamento');

        $this->assertEquals(0, (float) $conta->fresh()->valor_pago);
    }
}
