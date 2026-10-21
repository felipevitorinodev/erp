<?php

namespace Tests\Feature;

use App\Models\Comissoes\Comissao;
use App\Models\Empresas\Empresa;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Funcionarios\Funcionario;
use App\Models\Orcamentos\Orcamento;
use App\Models\OrdensServico\OrdemServico;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Repositories\Vendas\VendaRepository;
use App\Services\Orcamentos\OrcamentoService;
use App\Services\OrdensServico\OrdemServicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdemServicoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Produto $peca;

    private Produto $servico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['razao_social' => 'Assistência A', 'cnpj' => '11222333000181']);
        $this->peca = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Tela 6.1',
            'tipo' => 1,
            'preco_venda' => 300,
            'estoque_atual' => 5,
            'controla_estoque' => true,
        ]);
        $this->servico = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Mão de obra troca de tela',
            'tipo' => 2,
            'preco_venda' => 100,
            'estoque_atual' => 0,
            'controla_estoque' => false,
        ]);
        FormaPagamento::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Dinheiro',
            'tipo' => 'avista',
            'gera_conta_receber' => false,
            'ativo' => true,
        ]);
        FormaPagamento::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Boleto',
            'tipo' => 'prazo',
            'gera_conta_receber' => true,
            'dias_vencimento' => 30,
            'ativo' => true,
        ]);

        $this->actingAs(User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]));
    }

    public function test_abre_os_so_com_defeito_e_exige_equipamento(): void
    {
        $this->post(route('ordem-servico.store'), [
            'data_abertura' => now()->toDateString(),
            'defeito_relatado' => 'Não liga',
        ])->assertSessionHasErrors('equipamento');

        $this->post(route('ordem-servico.store'), [
            'data_abertura' => now()->toDateString(),
            'equipamento' => 'Celular',
            'numero_serie' => 'SN123',
            'defeito_relatado' => 'Não liga',
        ])->assertSessionHasNoErrors();

        $os = OrdemServico::firstOrFail();
        $this->assertSame('000001', $os->numero);
        $this->assertSame('aberta', $os->situacao);
        $this->assertEquals(0.0, (float) $os->total);
    }

    public function test_totais_sao_recalculados_no_servidor(): void
    {
        $this->post(route('ordem-servico.store'), [
            'data_abertura' => now()->toDateString(),
            'equipamento' => 'Celular',
            'desconto' => '50,00',
            'itens' => [
                ['produto_id' => $this->peca->id, 'quantidade' => '1', 'preco_unitario' => '300,00', 'desconto' => '0'],
                ['produto_id' => $this->servico->id, 'quantidade' => '1', 'preco_unitario' => '100,00', 'desconto' => '0'],
            ],
            'total' => '1,00',
        ])->assertSessionHasNoErrors();

        $os = OrdemServico::with('itens')->firstOrFail();
        $this->assertCount(2, $os->itens);
        $this->assertEquals(400.0, (float) $os->subtotal);
        $this->assertEquals(350.0, (float) $os->total);
    }

    public function test_faturar_a_vista_gera_venda_confirmada_e_baixa_so_a_peca(): void
    {
        $os = $this->osComItens('Dinheiro');
        $copiaAntiga = $os->fresh();

        $resposta = app(OrdemServicoService::class)->faturar($os);
        app(OrdemServicoService::class)->faturar($copiaAntiga);

        $this->assertSame(1, Venda::count());
        $venda = Venda::firstOrFail();
        $this->assertSame('confirmada', $venda->situacao);
        $this->assertEquals(400.0, (float) $venda->total);
        $this->assertStringContainsString('OS #' . $os->numero, (string) $venda->observacoes);
        $this->assertSame(route('venda.show', $venda), $resposta->getTargetUrl());

        $os->refresh();
        $this->assertSame('faturada', $os->situacao);
        $this->assertSame($venda->id, $os->venda_id);
        $this->assertNotNull($os->data_conclusao);
        $this->assertEquals(4.0, (float) $this->peca->fresh()->estoque_atual);
    }

    public function test_faturar_a_prazo_deixa_venda_em_andamento_sem_mexer_no_estoque(): void
    {
        $os = $this->osComItens('Boleto');

        app(OrdemServicoService::class)->faturar($os);

        $this->assertSame('em_andamento', Venda::firstOrFail()->situacao);
        $this->assertSame('faturada', $os->fresh()->situacao);
        $this->assertEquals(5.0, (float) $this->peca->fresh()->estoque_atual);
    }

    public function test_faturar_sem_itens_nao_gera_venda(): void
    {
        $os = OrdemServico::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_abertura' => now()->toDateString(),
            'equipamento' => 'Notebook',
            'situacao' => 'aberta',
        ]);

        $resposta = app(OrdemServicoService::class)->faturar($os);

        $this->assertSame(0, Venda::count());
        $this->assertSame('aberta', $os->fresh()->situacao);
        $this->assertStringContainsString('Adicione ao menos uma peça', session('error'));
        $this->assertSame(route('ordem-servico.show', $os), $resposta->getTargetUrl());
    }

    public function test_os_faturada_ou_cancelada_nao_aceita_edicao_nem_troca_de_situacao(): void
    {
        $os = $this->osComItens('Dinheiro');
        $this->post(route('ordem-servico.cancelar', $os))->assertSessionHas('success');

        $this->post(route('ordem-servico.situacao', $os), ['situacao' => 'em_execucao'])->assertSessionHas('error');
        $this->post(route('ordem-servico.faturar', $os))->assertSessionHas('error');
        $this->get(route('ordem-servico.edit', $os))->assertRedirect(route('ordem-servico.show', $os));

        $this->assertSame('cancelada', $os->fresh()->situacao);
        $this->assertSame(0, Venda::count());
    }

    public function test_troca_de_situacao_registra_conclusao(): void
    {
        $os = $this->osComItens('Dinheiro');

        $this->post(route('ordem-servico.situacao', $os), ['situacao' => 'concluida'])->assertSessionHas('success');
        $this->assertNotNull($os->fresh()->data_conclusao);

        $this->post(route('ordem-servico.situacao', $os), ['situacao' => 'em_execucao']);
        $this->assertNull($os->fresh()->data_conclusao);

        $this->post(route('ordem-servico.situacao', $os), ['situacao' => 'faturada'])->assertSessionHas('error');
        $this->assertSame('em_execucao', $os->fresh()->situacao);
    }

    public function test_orcamento_vira_os_uma_unica_vez(): void
    {
        $orcamento = Orcamento::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_orcamento' => now()->toDateString(),
            'subtotal' => 400,
            'desconto' => 20,
            'total' => 380,
            'forma_pagamento' => 'Dinheiro',
            'situacao' => 'pendente',
        ]);
        $orcamento->itens()->create([
            'produto_id' => $this->peca->id,
            'produto_nome' => 'Tela 6.1',
            'quantidade' => 1,
            'preco_unitario' => 300,
            'total' => 300,
        ]);
        $orcamento->itens()->create([
            'produto_id' => $this->servico->id,
            'produto_nome' => 'Mão de obra troca de tela',
            'quantidade' => 1,
            'preco_unitario' => 100,
            'total' => 100,
        ]);
        $copiaAntiga = $orcamento->fresh();

        $resposta = app(OrcamentoService::class)->gerarOrdemServico($orcamento);
        app(OrcamentoService::class)->gerarOrdemServico($copiaAntiga);

        $this->assertSame(1, OrdemServico::count());
        $os = OrdemServico::with('itens')->firstOrFail();
        $this->assertSame(route('ordem-servico.edit', $os), $resposta->getTargetUrl());
        $this->assertSame($orcamento->id, $os->orcamento_id);
        $this->assertCount(2, $os->itens);
        $this->assertEquals(380.0, (float) $os->total);
        $this->assertSame('Dinheiro', $os->forma_pagamento);

        $orcamento->refresh();
        $this->assertSame('aprovado', $orcamento->situacao);
        $this->assertSame($os->id, $orcamento->ordem_servico_id);
        $this->assertSame(0, Venda::count());
        $this->assertEquals(5.0, (float) $this->peca->fresh()->estoque_atual);
    }

    public function test_os_de_outra_empresa_retorna_404_e_sem_permissao_retorna_403(): void
    {
        $outra = Empresa::create(['razao_social' => 'Empresa B', 'cnpj' => '11444777000161']);
        $osAlheia = OrdemServico::create([
            'empresa_id' => $outra->id,
            'numero' => '000001',
            'data_abertura' => now()->toDateString(),
            'equipamento' => 'TV',
            'situacao' => 'aberta',
        ]);

        $this->get(route('ordem-servico.show', $osAlheia))->assertNotFound();
        $this->post(route('ordem-servico.faturar', $osAlheia))->assertNotFound();

        $this->actingAs(User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'vendedor',
            'ativo' => true,
        ]));
        $this->get(route('ordem-servico.index'))->assertForbidden();
    }

    public function test_telas_renderizam(): void
    {
        $os = $this->osComItens('Dinheiro');

        $this->get(route('ordem-servico.index'))->assertOk()->assertSee('#' . $os->numero);
        $this->get(route('ordem-servico.create'))->assertOk();
        $this->get(route('ordem-servico.show', $os))->assertOk()->assertSee('Celular');
        $this->get(route('ordem-servico.edit', $os))->assertOk()->assertSee('Tela 6.1');
        $this->get(route('ordem-servico.pdf', $os))->assertOk();

        $orcamento = Orcamento::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_orcamento' => now()->toDateString(),
            'situacao' => 'pendente',
        ]);
        $this->get(route('orcamento.show', $orcamento))->assertOk()->assertSee('Gerar OS')->assertSee('Gerar venda');

        $this->get(route('pdv.index'))->assertOk()->assertSee('pdv-pagamento', false)->assertSee('pdv-btn-finalizar', false);
    }

    public function test_comissao_do_tecnico_sobre_o_total_da_os(): void
    {
        $tecnico = $this->tecnico(geral: 5, os: 10, base: 'total');
        $os = $this->osComItens('Dinheiro', $tecnico->id);

        app(OrdemServicoService::class)->faturar($os);

        $comissao = Comissao::firstOrFail();
        $this->assertSame($tecnico->id, $comissao->funcionario_id);
        $this->assertSame($tecnico->id, Venda::firstOrFail()->funcionario_id);
        $this->assertEquals(10.0, (float) $comissao->percentual);
        $this->assertEquals(400.0, (float) $comissao->base_calculo);
        $this->assertEquals(40.0, (float) $comissao->valor);
    }

    public function test_comissao_do_tecnico_so_sobre_servicos_rateia_o_desconto(): void
    {
        $tecnico = $this->tecnico(geral: 5, os: 10, base: 'servicos');
        $os = $this->osComItens('Dinheiro', $tecnico->id);
        $os->update(['desconto' => 40, 'total' => 360]);

        app(OrdemServicoService::class)->faturar($os);

        $comissao = Comissao::firstOrFail();
        // serviço de 100 sobre subtotal 400; total 360 => base 90
        $this->assertEquals(90.0, (float) $comissao->base_calculo);
        $this->assertEquals(9.0, (float) $comissao->valor);
    }

    public function test_os_a_prazo_gera_comissao_de_os_quando_a_venda_e_confirmada(): void
    {
        $tecnico = $this->tecnico(geral: 5, os: 10, base: 'total');
        $os = $this->osComItens('Boleto', $tecnico->id);

        app(OrdemServicoService::class)->faturar($os);
        $this->assertSame(0, Comissao::count());

        app(VendaRepository::class)->confirmar(Venda::firstOrFail());

        $this->assertEquals(40.0, (float) Comissao::firstOrFail()->valor);
    }

    public function test_venda_comum_continua_usando_a_comissao_geral(): void
    {
        $tecnico = $this->tecnico(geral: 5, os: 10, base: 'servicos');
        $venda = app(VendaRepository::class)->store([
            'empresa_id' => $this->empresa->id,
            'funcionario_id' => $tecnico->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 400,
            'total' => 400,
            'forma_pagamento' => 'Dinheiro',
            'situacao' => 'em_andamento',
        ], [[
            'produto_id' => $this->servico->id,
            'produto_nome' => 'Mão de obra troca de tela',
            'quantidade' => 4,
            'preco_unitario' => 100,
            'total' => 400,
        ]]);

        app(VendaRepository::class)->confirmar($venda);

        $comissao = Comissao::firstOrFail();
        $this->assertEquals(5.0, (float) $comissao->percentual);
        $this->assertEquals(20.0, (float) $comissao->valor);
    }

    public function test_cadastro_do_funcionario_salva_regra_de_os(): void
    {
        $this->post(route('funcionario.store'), [
            'empresa_id' => $this->empresa->id,
            'nome' => 'Técnico Novo',
            'percentual_comissao' => '3.00',
            'comissao_os_percentual' => '12.50',
            'comissao_os_base' => 'servicos',
        ])->assertSessionHasNoErrors();

        $funcionario = Funcionario::where('nome', 'Técnico Novo')->firstOrFail();
        $this->assertEquals(12.5, (float) $funcionario->comissao_os_percentual);
        $this->assertSame('servicos', $funcionario->comissao_os_base);

        $this->post(route('funcionario.store'), [
            'empresa_id' => $this->empresa->id,
            'nome' => 'Sem regra',
            'comissao_os_base' => 'qualquer',
        ])->assertSessionHasErrors('comissao_os_base');
    }

    private function tecnico(float $geral, float $os, string $base): Funcionario
    {
        return Funcionario::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Técnico',
            'ativo' => true,
            'percentual_comissao' => $geral,
            'comissao_os_percentual' => $os,
            'comissao_os_base' => $base,
        ]);
    }

    private function osComItens(string $forma, ?int $tecnicoId = null): OrdemServico
    {
        $os = OrdemServico::create([
            'empresa_id' => $this->empresa->id,
            'funcionario_id' => $tecnicoId,
            'numero' => '000001',
            'data_abertura' => now()->toDateString(),
            'equipamento' => 'Celular',
            'subtotal' => 400,
            'total' => 400,
            'forma_pagamento' => $forma,
            'situacao' => 'em_execucao',
        ]);
        $os->itens()->create([
            'produto_id' => $this->peca->id,
            'produto_nome' => 'Tela 6.1',
            'quantidade' => 1,
            'preco_unitario' => 300,
            'total' => 300,
        ]);
        $os->itens()->create([
            'produto_id' => $this->servico->id,
            'produto_nome' => 'Mão de obra troca de tela',
            'quantidade' => 1,
            'preco_unitario' => 100,
            'total' => 100,
        ]);

        return $os;
    }
}
