<?php

namespace Tests\Feature;

use App\Models\CategoriasFinanceiras\CategoriaFinanceira;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatoriosTotaisTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private array $periodo = ['data_inicio' => '2026-10-01', 'data_fim' => '2026-10-31'];

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

    private function conta(array $dados): ContaReceber
    {
        return ContaReceber::create(array_merge([
            'empresa_id' => $this->empresa->id,
            'descricao' => 'Conta',
            'valor' => 100,
            'valor_pago' => 0,
            'data_vencimento' => '2026-10-10',
            'situacao' => 'aberta',
        ], $dados));
    }

    public function test_contas_somam_no_banco_por_categoria_incluindo_o_ultimo_dia(): void
    {
        $servicos = CategoriaFinanceira::create(['empresa_id' => $this->empresa->id, 'nome' => 'Serviços', 'tipo' => 'receita', 'ativo' => true]);
        $vendas = CategoriaFinanceira::create(['empresa_id' => $this->empresa->id, 'nome' => 'Vendas', 'tipo' => 'receita', 'ativo' => true]);

        $this->conta(['categoria_id' => $servicos->id, 'valor' => 100, 'valor_pago' => 40, 'situacao' => 'parcial']);
        $this->conta(['categoria_id' => $vendas->id, 'valor' => 200, 'data_vencimento' => '2026-10-31']);
        $this->conta(['valor' => 50]);
        $this->conta(['categoria_id' => $vendas->id, 'valor' => 999, 'situacao' => 'cancelada']);
        $this->conta(['categoria_id' => $vendas->id, 'valor' => 777, 'data_vencimento' => '2026-11-01']);

        $resposta = $this->get(route('relatorio.contas-receber', $this->periodo))->assertOk();

        $this->assertSame(350.0, $resposta->viewData('soma_valor'));
        $this->assertSame(40.0, $resposta->viewData('soma_pago'));
        $this->assertSame(4, $resposta->viewData('contas')->total());
        $this->assertSame([
            'Sem categoria' => ['nome' => 'Sem categoria', 'soma_valor' => 50.0, 'soma_pago' => 0.0],
            'Serviços' => ['nome' => 'Serviços', 'soma_valor' => 100.0, 'soma_pago' => 40.0],
            'Vendas' => ['nome' => 'Vendas', 'soma_valor' => 200.0, 'soma_pago' => 0.0],
        ], $resposta->viewData('subtotais_categoria')->all());

        $canceladas = $this->get(route('relatorio.contas-receber', $this->periodo + ['situacao' => 'cancelada']));
        $this->assertSame(999.0, $canceladas->viewData('soma_valor'));
    }

    public function test_vendas_e_produtos_mais_vendidos_incluem_o_ultimo_dia(): void
    {
        $produto = Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Produto', 'tipo' => 1, 'preco_venda' => 10]);

        foreach (['2026-10-01' => 'confirmada', '2026-10-31' => 'confirmada', '2026-11-01' => 'confirmada'] as $data => $situacao) {
            $venda = Venda::create([
                'empresa_id' => $this->empresa->id,
                'numero' => str_replace('-', '', $data),
                'data_venda' => $data,
                'situacao' => $situacao,
                'subtotal' => 30,
                'total' => 30,
            ]);
            VendaItem::create([
                'venda_id' => $venda->id,
                'produto_id' => $produto->id,
                'produto_nome' => 'Produto',
                'quantidade' => 3,
                'preco_unitario' => 10,
                'total' => 30,
            ]);
        }

        $vendas = $this->get(route('relatorio.vendas', $this->periodo))->assertOk();
        $this->assertSame(2, $vendas->viewData('vendas')->total());

        $produtos = $this->get(route('relatorio.produtos-mais-vendidos', $this->periodo))->assertOk();
        $this->assertSame(1, $produtos->viewData('produtos')->total());
        $this->assertEquals(6, $produtos->viewData('produtos')->first()->qtd_total);
    }

    public function test_exportacoes_csv_e_pdf_respondem_com_os_registros(): void
    {
        $this->conta(['descricao' => 'Recebimento X']);
        ContaPagar::create([
            'empresa_id' => $this->empresa->id,
            'descricao' => 'Pagamento Y',
            'valor' => 80,
            'valor_pago' => 0,
            'data_vencimento' => '2026-10-31',
            'situacao' => 'aberta',
        ]);
        Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Parafuso', 'tipo' => 1, 'controla_estoque' => true, 'ativo' => true]);

        $this->assertStringContainsString('Recebimento X', $this->get(route('relatorio.contas-receber.csv', $this->periodo))->streamedContent());
        $this->assertStringContainsString('Pagamento Y', $this->get(route('relatorio.contas-pagar.csv', $this->periodo))->streamedContent());
        $this->assertStringContainsString('Parafuso', $this->get(route('relatorio.estoque.csv'))->streamedContent());
        $this->get(route('relatorio.vendas.csv', $this->periodo))->assertOk();
        $this->get(route('relatorio.produtos-mais-vendidos.csv', $this->periodo))->assertOk();

        foreach (['vendas', 'produtos-mais-vendidos', 'contas-receber', 'contas-pagar'] as $relatorio) {
            $this->get(route("relatorio.{$relatorio}.pdf", $this->periodo))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
        $this->get(route('relatorio.estoque.pdf'))->assertOk();
    }
}
