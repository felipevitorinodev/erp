<?php

namespace Tests\Feature;

use App\Models\Clientes\Cliente;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\Fornecedores\Fornecedor;
use App\Models\Funcionarios\Funcionario;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Relatorios\CurvaAbc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatoriosOnda1Test extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa A',
            'cnpj' => '11222333000181',
        ]);
    }

    private function usuario(string $perfil): User
    {
        return User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => $perfil,
            'ativo' => true,
        ]);
    }

    /**
     * @return array{data_inicio: string, data_fim: string}
     */
    private function periodo(): array
    {
        return ['data_inicio' => '2026-10-01', 'data_fim' => '2026-10-31'];
    }

    private function venda(array $dados): Venda
    {
        return Venda::create(array_merge([
            'empresa_id' => $this->empresa->id,
            'numero' => 1,
            'data_venda' => '2026-10-20',
            'situacao' => 'confirmada',
            'subtotal' => 10,
            'total' => 10,
        ], $dados));
    }

    public function test_curva_abc_mantem_na_classe_o_item_que_cruza_o_corte(): void
    {
        $exato = CurvaAbc::classificar([
            ['cliente_id' => 1, 'valor' => 80],
            ['cliente_id' => 2, 'valor' => 15],
            ['cliente_id' => 3, 'valor' => 5],
        ]);
        $this->assertSame(['A', 'B', 'C'], array_column($exato, 'classe'));
        $this->assertEquals([80.0, 95.0, 100.0], array_column($exato, 'acumulado'));

        $atravessa = CurvaAbc::classificar([
            ['cliente_id' => 1, 'valor' => 70],
            ['cliente_id' => 2, 'valor' => 20],
            ['cliente_id' => 3, 'valor' => 10],
        ]);
        $this->assertSame(['A', 'A', 'B'], array_column($atravessa, 'classe'));

        $doisCortes = CurvaAbc::classificar([
            ['cliente_id' => 1, 'valor' => 96],
            ['cliente_id' => 2, 'valor' => 3],
            ['cliente_id' => 3, 'valor' => 1],
        ]);
        $this->assertSame(['A', 'C', 'C'], array_column($doisCortes, 'classe'));
    }

    public function test_vendas_por_vendedor_batem_com_as_confirmadas(): void
    {
        $outra = Empresa::create(['razao_social' => 'Empresa B', 'cnpj' => '22333444000181']);
        $ana = Funcionario::create(['empresa_id' => $this->empresa->id, 'nome' => 'Ana']);
        $bruno = Funcionario::create(['empresa_id' => $this->empresa->id, 'nome' => 'Bruno']);
        $externo = Funcionario::create(['empresa_id' => $outra->id, 'nome' => 'Vendedor externo']);

        $this->venda(['numero' => 1, 'funcionario_id' => $ana->id, 'total' => 100, 'subtotal' => 100]);
        $this->venda(['numero' => 2, 'funcionario_id' => $bruno->id, 'total' => 50, 'subtotal' => 50]);
        $this->venda(['numero' => 3, 'funcionario_id' => $bruno->id, 'total' => 20, 'subtotal' => 20, 'situacao' => 'cancelada']);
        $this->venda(['numero' => 4, 'total' => 30, 'subtotal' => 30]);
        $this->venda(['empresa_id' => $outra->id, 'numero' => 9, 'total' => 999, 'subtotal' => 999]);

        $this->actingAs($this->usuario('admin'));

        $vendas = $this->get(route('relatorio.vendas', $this->periodo()))->assertOk();
        $pagina = $this->get(route('relatorio.vendas-por-vendedor', $this->periodo()))->assertOk();
        $totais = $pagina->viewData('totais');
        $nomes = array_column($pagina->viewData('vendedores')->items(), 'nome');

        $this->assertEquals($vendas->viewData('totais')['valor_confirmadas'], $totais['faturamento']);
        $this->assertEquals(180, $totais['faturamento']);
        $this->assertSame(3, $totais['qtd_confirmadas']);
        $this->assertEquals(60, $totais['ticket']);
        $this->assertSame(1, $totais['qtd_sem_vendedor']);
        $this->assertEquals(30, $totais['faturamento_sem_vendedor']);
        $this->assertEquals(1, $totais['qtd_canceladas']);
        $this->assertContains('Sem vendedor', $nomes);
        $pagina->assertSee('Ana')->assertSee('Bruno')->assertDontSee('Vendedor externo');

        $this->get(route('relatorio.vendas-por-vendedor', array_merge($this->periodo(), [
            'funcionario_id' => $externo->id,
        ])))
            ->assertOk()
            ->assertSee('Ana');

        $csv = $this->get(route('relatorio.vendas-por-vendedor.csv', $this->periodo()))->assertOk();
        $conteudo = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $conteudo);
        $this->assertStringContainsString('Ana;', $conteudo);
        $this->assertStringContainsString(';', $conteudo);

        $this->get(route('relatorio.vendas-por-vendedor.pdf', $this->periodo()))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_dia_da_semana_soma_as_confirmadas_e_preenche_a_semana(): void
    {
        $outra = Empresa::create(['razao_social' => 'Empresa B', 'cnpj' => '22333444000181']);
        $this->venda(['numero' => 1, 'data_venda' => '2026-10-20', 'total' => 40, 'subtotal' => 40]);
        $this->venda(['numero' => 2, 'data_venda' => '2026-10-25', 'total' => 10, 'subtotal' => 10]);
        $this->venda(['numero' => 3, 'data_venda' => '2026-10-20', 'total' => 15, 'subtotal' => 15, 'situacao' => 'cancelada']);
        $this->venda(['empresa_id' => $outra->id, 'numero' => 9, 'data_venda' => '2026-10-20', 'total' => 999, 'subtotal' => 999]);

        $this->actingAs($this->usuario('admin'));

        $vendas = $this->get(route('relatorio.vendas', $this->periodo()))->assertOk();
        $pagina = $this->get(route('relatorio.vendas-por-dia-semana', $this->periodo()))->assertOk();
        $linhas = $pagina->viewData('dias')->items();
        $porNome = collect($linhas)->keyBy('nome');

        $this->assertSame(
            ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'],
            array_column($linhas, 'nome')
        );
        $this->assertEquals(40, $porNome['Terça']['valor']);
        $this->assertEquals(10, $porNome['Domingo']['valor']);
        $this->assertSame(0, $porNome['Segunda']['qtd']);
        $this->assertEquals($vendas->viewData('totais')['valor_confirmadas'], $pagina->viewData('totais')['faturamento']);
        $this->assertSame('Terça', $pagina->viewData('totais')['pico']);

        $resumo = $this->getJson(route('relatorio.vendas-por-dia-semana.resumo', $this->periodo()))->assertOk()->json();
        $this->assertSame(['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'], $resumo['graficos'][0]['labels']);
        $this->assertEquals(50, array_sum($resumo['graficos'][0]['series'][0]['dados']));
        $this->assertEquals(50, array_sum($resumo['graficos'][1]['series'][0]['dados']));
    }

    public function test_ranking_de_clientes_usa_o_corte_80_15_5(): void
    {
        $outra = Empresa::create(['razao_social' => 'Empresa B', 'cnpj' => '22333444000181']);
        $alfa = Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cliente Alfa']);
        $beta = Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cliente Beta']);
        $gama = Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cliente Gama']);
        $externo = Cliente::create(['empresa_id' => $outra->id, 'nome' => 'Cliente Externo']);

        $this->venda(['numero' => 1, 'cliente_id' => $alfa->id, 'total' => 800, 'subtotal' => 800]);
        $this->venda(['numero' => 2, 'cliente_id' => $beta->id, 'total' => 150, 'subtotal' => 150]);
        $this->venda(['numero' => 3, 'cliente_id' => $gama->id, 'total' => 50, 'subtotal' => 50]);
        $this->venda(['numero' => 4, 'total' => 999, 'subtotal' => 999, 'situacao' => 'cancelada']);
        $this->venda(['empresa_id' => $outra->id, 'numero' => 9, 'cliente_id' => $externo->id, 'total' => 700, 'subtotal' => 700]);

        $this->actingAs($this->usuario('admin'));

        $vendas = $this->get(route('relatorio.vendas', $this->periodo()))->assertOk();
        $pagina = $this->get(route('relatorio.ranking-clientes', $this->periodo()))->assertOk();
        $itens = $pagina->viewData('ranking')->items();

        $this->assertSame(['Cliente Alfa', 'Cliente Beta', 'Cliente Gama'], array_column($itens, 'nome'));
        $this->assertSame(['A', 'B', 'C'], array_column($itens, 'classe'));
        $this->assertEquals([80.0, 15.0, 5.0], array_column($itens, 'percentual'));
        $this->assertEquals(1000, $pagina->viewData('totais')['faturamento']);
        $this->assertEquals($vendas->viewData('totais')['valor_confirmadas'], $pagina->viewData('totais')['faturamento']);

        $classeA = $this->get(route('relatorio.ranking-clientes', array_merge($this->periodo(), ['classe' => 'A'])))->assertOk();
        $this->assertSame(['Cliente Alfa'], array_column($classeA->viewData('ranking')->items(), 'nome'));
        $this->assertEquals(1000, $classeA->viewData('totais')['faturamento']);
        $this->assertEquals(800, $classeA->viewData('totais')['faturamento_lista']);

        $this->get(route('relatorio.ranking-clientes', array_merge($this->periodo(), [
            'cliente_id' => $externo->id,
        ])))
            ->assertOk()
            ->assertSee('Cliente Alfa');

        $resumo = $this->getJson(route('relatorio.ranking-clientes.resumo', $this->periodo()))->assertOk()->json();
        $this->assertSame('Cliente Alfa', $resumo['graficos'][1]['labels'][0]);
        $this->assertEquals(1000, array_sum($resumo['graficos'][0]['series'][0]['dados']));
    }

    public function test_clientes_com_debito_somam_o_saldo_em_aberto(): void
    {
        $this->travelTo('2026-11-01 12:00:00');
        $outra = Empresa::create(['razao_social' => 'Empresa B', 'cnpj' => '22333444000181']);
        $alfa = Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cliente Alfa']);
        $beta = Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cliente Beta']);

        ContaReceber::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $alfa->id, 'descricao' => 'Atraso Alfa', 'valor' => 100, 'valor_pago' => 0, 'data_vencimento' => '2026-10-01', 'situacao' => 'aberta']);
        ContaReceber::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $alfa->id, 'descricao' => 'Vence hoje', 'valor' => 80, 'valor_pago' => 30, 'data_vencimento' => '2026-11-01', 'situacao' => 'parcial']);
        ContaReceber::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $beta->id, 'descricao' => 'Futuro Beta', 'valor' => 40, 'valor_pago' => 0, 'data_vencimento' => '2026-12-01', 'situacao' => 'aberta']);
        ContaReceber::create(['empresa_id' => $this->empresa->id, 'descricao' => 'Sem cliente', 'valor' => 10, 'valor_pago' => 0, 'data_vencimento' => '2026-10-01', 'situacao' => 'aberta']);
        ContaReceber::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $alfa->id, 'descricao' => 'Cancelada', 'valor' => 999, 'valor_pago' => 0, 'data_vencimento' => '2026-10-01', 'situacao' => 'cancelada']);
        ContaReceber::create(['empresa_id' => $this->empresa->id, 'cliente_id' => $alfa->id, 'descricao' => 'Paga', 'valor' => 200, 'valor_pago' => 200, 'data_vencimento' => '2026-10-01', 'situacao' => 'paga']);
        ContaReceber::create(['empresa_id' => $outra->id, 'descricao' => 'Outra empresa', 'valor' => 500, 'valor_pago' => 0, 'data_vencimento' => '2026-10-01', 'situacao' => 'aberta']);

        $pagina = $this->actingAs($this->usuario('admin'))
            ->get(route('relatorio.clientes-com-debito'))
            ->assertOk();
        $totais = $pagina->viewData('totais');
        $porNome = collect($pagina->viewData('debitos')->items())->keyBy('nome');

        $this->assertEquals(200, $totais['saldo']);
        $this->assertEquals(110, $totais['vencido']);
        $this->assertSame(3, $totais['qtd_clientes']);
        $this->assertSame(4, $totais['qtd']);
        $this->assertEquals(150, $porNome['Cliente Alfa']['saldo']);
        $this->assertEquals(100, $porNome['Cliente Alfa']['vencido']);
        $this->assertEquals(0, $porNome['Cliente Beta']['vencido']);
        $this->assertEquals(10, $porNome['Sem cliente']['vencido']);
        $pagina->assertDontSee('Outra empresa')->assertDontSee('Cancelada');
    }

    public function test_inadimplencia_separa_faixas_e_ignora_o_vencimento_de_hoje(): void
    {
        $this->travelTo('2026-11-01 12:00:00');
        $outra = Empresa::create(['razao_social' => 'Empresa B', 'cnpj' => '22333444000181']);
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cliente Alfa']);
        $fornecedor = Fornecedor::create(['empresa_id' => $this->empresa->id, 'nome' => 'Fornecedor Alfa']);

        $titulos = [
            ['descricao' => 'atraso-10', 'valor' => 100, 'valor_pago' => 0, 'data_vencimento' => '2026-10-22', 'situacao' => 'aberta'],
            ['descricao' => 'atraso-40', 'valor' => 80, 'valor_pago' => 20, 'data_vencimento' => '2026-09-22', 'situacao' => 'parcial'],
            ['descricao' => 'atraso-78', 'valor' => 40, 'valor_pago' => 0, 'data_vencimento' => '2026-08-15', 'situacao' => 'aberta'],
            ['descricao' => 'atraso-92', 'valor' => 25, 'valor_pago' => 0, 'data_vencimento' => '2026-08-01', 'situacao' => 'aberta'],
            ['descricao' => 'vence-hoje', 'valor' => 999, 'valor_pago' => 0, 'data_vencimento' => '2026-11-01', 'situacao' => 'aberta'],
            ['descricao' => 'futuro', 'valor' => 888, 'valor_pago' => 0, 'data_vencimento' => '2026-11-10', 'situacao' => 'aberta'],
            ['descricao' => 'titulo-pago', 'valor' => 777, 'valor_pago' => 777, 'data_vencimento' => '2026-10-01', 'situacao' => 'paga'],
            ['descricao' => 'titulo-cancelado', 'valor' => 666, 'valor_pago' => 0, 'data_vencimento' => '2026-10-01', 'situacao' => 'cancelada'],
        ];
        foreach ($titulos as $titulo) {
            ContaReceber::create(array_merge($titulo, [
                'empresa_id' => $this->empresa->id,
                'cliente_id' => $cliente->id,
            ]));
        }
        ContaReceber::create([
            'empresa_id' => $outra->id,
            'descricao' => 'titulo-externo',
            'valor' => 555,
            'valor_pago' => 0,
            'data_vencimento' => '2026-10-01',
            'situacao' => 'aberta',
        ]);
        ContaPagar::create([
            'empresa_id' => $this->empresa->id,
            'fornecedor_id' => $fornecedor->id,
            'descricao' => 'pagar-atrasado',
            'valor' => 15,
            'valor_pago' => 0,
            'data_vencimento' => '2026-10-01',
            'situacao' => 'aberta',
        ]);

        $this->actingAs($this->usuario('admin'));

        $pagina = $this->get(route('relatorio.inadimplencia-receber'))->assertOk();
        $totais = $pagina->viewData('totais');
        $this->assertEquals(225, $totais['saldo']);
        $this->assertSame(4, $totais['qtd']);
        $this->assertEquals(100, $totais['faixas']['1-30']['saldo']);
        $this->assertEquals(60, $totais['faixas']['31-60']['saldo']);
        $this->assertEquals(40, $totais['faixas']['61-90']['saldo']);
        $this->assertEquals(25, $totais['faixas']['91+']['saldo']);
        $this->assertEquals(225, round(array_sum(array_column($totais['faixas'], 'saldo')), 2));
        $pagina->assertSee('atraso-10')->assertDontSee('vence-hoje')->assertDontSee('pagar-atrasado');

        $dados = $this->getJson(route('relatorio.inadimplencia-receber.dados'))->assertOk()->json();
        $porDescricao = collect($dados['linhas'])->keyBy('descricao');
        $this->assertSame(10, $porDescricao['atraso-10']['dias']);
        $this->assertSame('1-30', $porDescricao['atraso-10']['faixa']);
        $this->assertSame('31-60', $porDescricao['atraso-40']['faixa']);
        $this->assertEquals(60, $porDescricao['atraso-40']['saldo']);
        $this->assertSame('61-90', $porDescricao['atraso-78']['faixa']);
        $this->assertSame('91+', $porDescricao['atraso-92']['faixa']);

        $resumo = $this->getJson(route('relatorio.inadimplencia-receber.resumo'))->assertOk()->json();
        $this->assertSame(['08/2026', '09/2026', '10/2026'], $resumo['graficos'][1]['labels']);
        $this->assertEquals(225, array_sum($resumo['graficos'][0]['series'][0]['dados']));
        $this->assertEquals(225, array_sum($resumo['graficos'][1]['series'][0]['dados']));

        $faixa = $this->get(route('relatorio.inadimplencia-receber', ['faixa' => '31-60']))->assertOk();
        $this->assertEquals(60, $faixa->viewData('totais')['saldo']);
        $faixa->assertSee('atraso-40')->assertDontSee('atraso-10');

        $pagar = $this->get(route('relatorio.inadimplencia-pagar'))->assertOk();
        $this->assertEquals(15, $pagar->viewData('totais')['saldo']);
        $pagar->assertSee('pagar-atrasado')->assertDontSee('atraso-10');
    }

    public function test_relatorios_financeiros_novos_exigem_as_duas_permissoes(): void
    {
        Perfil::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'slug' => 'analista'],
            ['nome' => 'Analista', 'permissoes' => ['relatorios'], 'sistema' => false, 'ativo' => true],
        );

        $analista = $this->usuario('analista');

        $this->actingAs($analista)->get(route('relatorio.ranking-clientes'))->assertOk();
        $this->actingAs($analista)->get(route('relatorio.inadimplencia-receber'))->assertForbidden();
        $this->actingAs($analista)->get(route('relatorio.inadimplencia-pagar'))->assertForbidden();
        $this->actingAs($analista)->get(route('relatorio.clientes-com-debito'))->assertForbidden();
        $this->actingAs($analista)->getJson(route('relatorio.inadimplencia-receber.resumo'))->assertForbidden();
        $this->actingAs($analista)->getJson(route('relatorio.clientes-com-debito.resumo'))->assertForbidden();
    }
}
