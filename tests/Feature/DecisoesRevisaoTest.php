<?php

namespace Tests\Feature;

use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\Estoque\EntradaEstoque;
use App\Models\Estoque\EntradaEstoqueItem;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Orcamentos\Orcamento;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Repositories\Estoque\EntradaEstoqueRepository;
use App\Repositories\Vendas\VendaRepository;
use App\Services\Orcamentos\OrcamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecisoesRevisaoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['razao_social' => 'Empresa A', 'cnpj' => '11222333000181']);
        $this->produto = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Produto',
            'tipo' => 1,
            'preco_venda' => 10,
            'estoque_atual' => 8,
            'controla_estoque' => true,
        ]);
        $this->actingAs(User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]));
    }

    private function venda(string $formaPagamento): Venda
    {
        return app(VendaRepository::class)->store([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 10,
            'total' => 10,
            'forma_pagamento' => $formaPagamento,
            'situacao' => 'em_andamento',
        ], [
            ['produto_id' => $this->produto->id, 'produto_nome' => 'Produto', 'quantidade' => 1, 'preco_unitario' => 10, 'desconto' => 0, 'total' => 10],
        ]);
    }

    public function test_timezone_padrao_e_sao_paulo(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
    }

    public function test_venda_a_prazo_gera_conta_mesmo_apos_renomear_a_forma(): void
    {
        $forma = FormaPagamento::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Boleto',
            'tipo' => 'prazo',
            'dias_vencimento' => 30,
            'ativo' => true,
        ]);
        $venda = $this->venda('boleto');
        $this->assertSame($forma->id, $venda->forma_pagamento_id);

        $forma->update(['nome' => 'Boleto bancário']);
        app(VendaRepository::class)->confirmar($venda->fresh()->load('itens'));

        $this->assertSame('confirmada', $venda->fresh()->situacao);
        $this->assertSame(1, ContaReceber::where('venda_id', $venda->id)->count());
    }

    public function test_venda_com_forma_desconhecida_nao_e_confirmada(): void
    {
        $venda = $this->venda('Forma que não existe');
        $this->assertNull($venda->forma_pagamento_id);

        try {
            app(VendaRepository::class)->confirmar($venda->fresh()->load('itens'));
            $this->fail('A confirmação deveria ser recusada.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('não encontrada', $e->getMessage());
        }

        $this->assertSame('em_andamento', $venda->fresh()->situacao);
        $this->assertEquals(8.0, (float) $this->produto->fresh()->estoque_atual);
    }

    public function test_venda_sem_forma_continua_podendo_ser_confirmada(): void
    {
        $venda = $this->venda('');

        app(VendaRepository::class)->confirmar($venda->fresh()->load('itens'));

        $this->assertSame('confirmada', $venda->fresh()->situacao);
        $this->assertSame(0, ContaReceber::count());
    }

    public function test_venda_do_orcamento_usa_a_data_da_aprovacao(): void
    {
        $orcamento = Orcamento::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_orcamento' => now()->subDays(10)->toDateString(),
            'subtotal' => 10,
            'total' => 10,
            'situacao' => 'pendente',
        ]);
        $orcamento->itens()->create([
            'produto_id' => $this->produto->id,
            'produto_nome' => 'Produto',
            'quantidade' => 1,
            'preco_unitario' => 10,
            'total' => 10,
        ]);

        app(OrcamentoService::class)->aprovar($orcamento);

        $this->assertSame(now()->toDateString(), Venda::firstOrFail()->data_venda->toDateString());
    }

    public function test_conta_a_pagar_da_entrada_importada_usa_o_valor_da_nota(): void
    {
        $entrada = EntradaEstoque::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '123',
            'data_entrada' => now()->toDateString(),
            'subtotal' => 50,
            'total' => 50,
            'valor_nota' => 57.35,
            'situacao' => 'rascunho',
        ]);
        EntradaEstoqueItem::create([
            'entrada_estoque_id' => $entrada->id,
            'produto_id' => $this->produto->id,
            'produto_nome' => 'Produto',
            'quantidade' => 5,
            'preco_unitario' => 10,
            'total' => 50,
        ]);

        app(EntradaEstoqueRepository::class)->confirmar($entrada->load('itens'));

        $this->assertEquals(57.35, (float) ContaPagar::where('entrada_estoque_id', $entrada->id)->value('valor'));
    }

    public function test_totais_do_relatorio_de_contas_ignoram_canceladas(): void
    {
        foreach ([['valor' => 100, 'situacao' => 'aberta'], ['valor' => 30, 'situacao' => 'cancelada']] as $conta) {
            ContaReceber::create([
                'empresa_id' => $this->empresa->id,
                'descricao' => 'Conta',
                'valor' => $conta['valor'],
                'valor_pago' => 0,
                'data_vencimento' => now()->toDateString(),
                'situacao' => $conta['situacao'],
            ]);
        }
        $periodo = ['data_inicio' => now()->toDateString(), 'data_fim' => now()->toDateString()];

        $this->get(route('relatorio.contas-receber', $periodo))->assertViewHas('soma_valor', 100.0);
        $this->get(route('relatorio.contas-receber', $periodo + ['situacao' => 'cancelada']))->assertViewHas('soma_valor', 30.0);
    }
}
