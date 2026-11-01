<?php

namespace Tests\Feature;

use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\Grupos\Grupo;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RelatoriosConsultaTest extends TestCase
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

    public function test_periodo_personalizado_acima_de_366_dias_volta_com_erro(): void
    {
        $this->actingAs($this->usuario('admin'))
            ->get(route('relatorio.vendas', [
                'data_inicio' => '2024-01-01',
                'data_fim' => '2025-01-01',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('data_fim');
    }

    public function test_ano_bissexto_de_366_dias_continua_valido(): void
    {
        $this->actingAs($this->usuario('admin'))
            ->get(route('relatorio.vendas', [
                'data_inicio' => '2024-01-01',
                'data_fim' => '2024-12-31',
            ]))
            ->assertOk();
    }

    public function test_atalho_de_vencidos_aceita_o_historico_desde_2000(): void
    {
        $this->actingAs($this->usuario('admin'))
            ->get(route('relatorio.contas-receber', [
                'vencimento' => 'vencidos',
                'data_inicio' => '2000-01-01',
                'data_fim' => '2026-11-01',
            ]))
            ->assertOk()
            ->assertSee('Vencidos', false);
    }

    public function test_resumo_e_dados_de_vendas_usam_somente_a_empresa_e_o_faturamento_confirmado(): void
    {
        $outra = Empresa::create([
            'razao_social' => 'Empresa B',
            'cnpj' => '22333444000181',
        ]);
        $clienteOutra = Cliente::create([
            'empresa_id' => $outra->id,
            'nome' => 'Cliente de outra empresa',
        ]);

        Venda::create([
            'empresa_id' => $outra->id,
            'numero' => 9,
            'data_venda' => '2026-10-15',
            'situacao' => 'confirmada',
            'subtotal' => 999,
            'total' => 999,
        ]);
        Venda::create([
            'empresa_id' => $this->empresa->id,
            'numero' => 1,
            'data_venda' => '2026-10-15',
            'situacao' => 'confirmada',
            'subtotal' => 40,
            'total' => 40,
        ]);
        Venda::create([
            'empresa_id' => $this->empresa->id,
            'numero' => 2,
            'data_venda' => '2026-10-16',
            'situacao' => 'cancelada',
            'subtotal' => 15,
            'total' => 15,
        ]);

        $this->actingAs($this->usuario('admin'));

        $pagina = $this->get(route('relatorio.vendas', $this->periodo()))->assertOk();
        $this->assertSame(40.0, $pagina->viewData('totais')['valor_confirmadas']);
        $this->assertSame(2, $pagina->viewData('vendas')->total());

        $dados = $this->getJson(route('relatorio.vendas.dados', $this->periodo()))
            ->assertOk()
            ->json();
        $this->assertSame($pagina->viewData('vendas')->total(), $dados['total']);
        $this->assertEquals(40, $dados['totais']['valor_confirmadas']);

        $resumo = $this->getJson(route('relatorio.vendas.resumo', $this->periodo()))
            ->assertOk()
            ->json();
        $this->assertCount(2, $resumo['graficos']);
        $this->assertSame('Faturamento das confirmadas', $resumo['graficos'][0]['titulo']);
        $this->assertEquals(40, round(array_sum($resumo['graficos'][0]['series'][0]['dados']), 2));

        $ignorado = $this->get(route('relatorio.vendas', $this->periodo() + [
            'cliente_id' => $clienteOutra->id,
        ]));
        $this->assertSame(2, $ignorado->viewData('vendas')->total());
    }

    public function test_resumo_de_periodo_longo_agrupa_por_mes(): void
    {
        Venda::create([
            'empresa_id' => $this->empresa->id,
            'numero' => 1,
            'data_venda' => '2026-03-10',
            'situacao' => 'confirmada',
            'subtotal' => 80,
            'total' => 80,
        ]);

        $this->actingAs($this->usuario('admin'));

        $resumo = $this->getJson(route('relatorio.vendas.resumo', [
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-10-31',
        ]))->assertOk()->json();

        $this->assertContains('03/2026', $resumo['graficos'][0]['labels']);
        $this->assertEquals(80, round(array_sum($resumo['graficos'][0]['series'][0]['dados']), 2));
    }

    public function test_resumo_de_estoque_agrupa_o_custo_por_grupo(): void
    {
        $grupo = Grupo::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Ferragens',
            'ativo' => true,
        ]);
        Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Parafuso',
            'tipo' => 1,
            'grupo_id' => $grupo->id,
            'controla_estoque' => true,
            'ativo' => true,
            'estoque_atual' => 4,
            'estoque_minimo' => 1,
            'preco_custo' => 2.5,
        ]);

        $this->actingAs($this->usuario('admin'));

        DB::enableQueryLog();
        $resumo = $this->getJson(route('relatorio.estoque.resumo'))->assertOk()->json();
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        $this->assertStringContainsString('produtos"."empresa_id', $sql);
        $this->assertStringContainsString('produtos"."ativo', $sql);

        $this->assertSame('Valor em custo por grupo', $resumo['graficos'][0]['titulo']);
        $this->assertSame(['Ferragens'], $resumo['graficos'][0]['labels']);
        $this->assertEquals(10, $resumo['graficos'][0]['series'][0]['dados'][0]);
        $this->assertSame('Situação do estoque', $resumo['graficos'][1]['titulo']);
    }

    public function test_usuario_sem_financeiro_nao_consulta_o_resumo_de_contas(): void
    {
        Perfil::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'slug' => 'analista'],
            ['nome' => 'Analista', 'permissoes' => ['relatorios'], 'sistema' => false, 'ativo' => true],
        );

        $this->actingAs($this->usuario('analista'))
            ->getJson(route('relatorio.contas-receber.resumo'))
            ->assertForbidden();
    }

    public function test_data_invalida_no_json_responde_422(): void
    {
        $this->actingAs($this->usuario('admin'))
            ->getJson(route('relatorio.vendas.resumo', ['data_inicio' => 'abc']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('data_inicio');
    }

    public function test_busca_de_opcoes_nao_vaza_cliente_de_outra_empresa(): void
    {
        $outra = Empresa::create([
            'razao_social' => 'Empresa B',
            'cnpj' => '22333444000181',
        ]);
        Cliente::create(['empresa_id' => $outra->id, 'nome' => 'Segredo Externo']);
        Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cliente Local']);

        $this->actingAs($this->usuario('admin'));

        $this->getJson(route('relatorio.opcoes.clientes', ['q' => 'Segredo']))
            ->assertOk()
            ->assertExactJson([]);

        $local = $this->getJson(route('relatorio.opcoes.clientes', ['q' => 'Local']))
            ->assertOk()
            ->json();

        $this->assertSame(['Cliente Local'], array_column($local, 'nome'));
        $this->assertArrayNotHasKey('preco_custo', $local[0]);
    }
}
