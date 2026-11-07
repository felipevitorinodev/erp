<?php

namespace Tests\Feature;

use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatoriosKpisTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        $this->empresa = Empresa::create(['razao_social' => 'Empresa A', 'cnpj' => '11222333000181']);
        $this->actingAs(User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]));
    }

    private function periodo(): array
    {
        return ['data_inicio' => '2026-10-01', 'data_fim' => '2026-10-31'];
    }

    public function test_vendas_separam_totais_por_situacao_e_calculam_ticket_medio(): void
    {
        $vendas = [
            ['confirmada', 100], ['confirmada', 50.5], ['confirmada', 0.5],
            ['em_andamento', 40], ['cancelada', 70], ['cancelada', 30],
        ];
        foreach ($vendas as $i => [$situacao, $total]) {
            Venda::create([
                'empresa_id' => $this->empresa->id,
                'numero' => $i + 1,
                'data_venda' => '2026-10-15',
                'situacao' => $situacao,
                'subtotal' => $total,
                'total' => $total,
            ]);
        }

        $resposta = $this->get(route('relatorio.vendas', $this->periodo()))
            ->assertOk()
            ->assertSee('kpi-card', false);

        $totais = $resposta->viewData('totais');
        $this->assertSame(6, $totais['registros']);
        $this->assertSame(3, $totais['qtd_confirmadas']);
        $this->assertSame(151.0, $totais['valor_confirmadas']);
        $this->assertSame(50.33, $totais['ticket_medio']);
        $this->assertSame(1, $totais['qtd_em_andamento']);
        $this->assertSame(40.0, $totais['valor_em_andamento']);
        $this->assertSame(2, $totais['qtd_canceladas']);
        $this->assertSame(100.0, $totais['valor_canceladas']);
        $this->assertSame(6, $resposta->viewData('vendas')->total());

        $filtrado = $this->get(route('relatorio.vendas', $this->periodo() + ['situacao' => 'cancelada']))->viewData('totais');
        $this->assertSame(0.0, $filtrado['valor_confirmadas']);
        $this->assertSame(0.0, $filtrado['ticket_medio']);
        $this->assertSame(100.0, $filtrado['valor_canceladas']);
    }

    public function test_contas_calculam_saldo_em_aberto_e_vencido_sem_canceladas(): void
    {
        $contas = [
            ['valor' => 100, 'valor_pago' => 40, 'situacao' => 'parcial', 'data_vencimento' => '2026-10-10'],
            ['valor' => 200, 'valor_pago' => 0, 'situacao' => 'aberta', 'data_vencimento' => '2026-10-25'],
            ['valor' => 80, 'valor_pago' => 80, 'situacao' => 'paga', 'data_vencimento' => '2026-10-05'],
            ['valor' => 999, 'valor_pago' => 0, 'situacao' => 'cancelada', 'data_vencimento' => '2026-10-05'],
            ['valor' => 30, 'valor_pago' => 0, 'situacao' => 'aberta', 'data_vencimento' => '2026-10-20'],
        ];
        foreach ($contas as $conta) {
            ContaReceber::create($conta + ['empresa_id' => $this->empresa->id, 'descricao' => 'Conta']);
        }

        $resposta = $this->get(route('relatorio.contas-receber', $this->periodo()))->assertOk();
        $totais = $resposta->viewData('totais');

        $this->assertSame(5, $totais['registros']);
        $this->assertSame(5, $resposta->viewData('contas')->total());
        $this->assertSame(4, $totais['qtd']);
        $this->assertSame(410.0, $totais['soma_valor']);
        $this->assertSame(120.0, $totais['soma_pago']);
        $this->assertSame(29.3, $totais['percentual_pago']);
        $this->assertSame(3, $totais['qtd_aberto']);
        $this->assertSame(290.0, $totais['saldo_aberto']);
        // Vencida só antes de hoje: a de 20/10 (hoje) ainda não conta.
        $this->assertSame(1, $totais['qtd_vencido']);
        $this->assertSame(60.0, $totais['saldo_vencido']);
        $this->assertSame(['nome' => 'Sem categoria', 'soma_valor' => 410.0, 'soma_pago' => 120.0], $resposta->viewData('subtotais_categoria')->first());

        $this->get(route('relatorio.contas-receber.pdf', $this->periodo()))->assertOk();
    }

    public function test_estoque_e_produtos_mais_vendidos_totalizam_com_os_filtros(): void
    {
        $base = ['empresa_id' => $this->empresa->id, 'tipo' => 1, 'controla_estoque' => true, 'ativo' => true];
        $parafuso = Produto::create($base + ['nome' => 'Parafuso', 'estoque_atual' => 10, 'estoque_minimo' => 2, 'preco_custo' => 1.5]);
        Produto::create($base + ['nome' => 'Porca', 'estoque_atual' => 2, 'estoque_minimo' => 5, 'preco_custo' => 3]);
        Produto::create($base + ['nome' => 'Arruela', 'estoque_atual' => -4, 'estoque_minimo' => 0, 'preco_custo' => 9]);
        Produto::create(['nome' => 'Inativo', 'ativo' => false, 'estoque_atual' => 100, 'preco_custo' => 100] + $base);

        $estoque = $this->get(route('relatorio.estoque'))->assertOk();
        $this->assertSame(['produtos' => 3, 'criticos' => 2, 'zerados' => 1, 'valor_custo' => 21.0], $estoque->viewData('totais'));
        $this->assertSame(3, $estoque->viewData('produtos')->total());
        $this->get(route('relatorio.estoque.pdf'))->assertOk();

        foreach (['confirmada' => 2, 'cancelada' => 7] as $situacao => $quantidade) {
            $venda = Venda::create([
                'empresa_id' => $this->empresa->id,
                'numero' => $quantidade,
                'data_venda' => '2026-10-15',
                'situacao' => $situacao,
                'subtotal' => $quantidade * 5,
                'total' => $quantidade * 5,
            ]);
            VendaItem::create([
                'venda_id' => $venda->id,
                'produto_id' => $parafuso->id,
                'produto_nome' => 'Parafuso',
                'quantidade' => $quantidade,
                'preco_unitario' => 5,
                'total' => $quantidade * 5,
            ]);
        }

        $maisVendidos = $this->get(route('relatorio.produtos-mais-vendidos', $this->periodo()))->assertOk();
        $this->assertSame(['produtos' => 1, 'qtd_total' => 2.0, 'valor_total' => 10.0], $maisVendidos->viewData('totais'));
        $this->assertSame(1, $maisVendidos->viewData('produtos')->total());
        $this->get(route('relatorio.produtos-mais-vendidos.pdf', $this->periodo()))->assertOk();
    }
}
