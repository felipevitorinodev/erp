<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Nfes\Nfe;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Services\Nfes\NfePendenciasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NfeRascunhoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '11222333000181',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
            'nfe_destino' => 1,
            'crt' => 1,
        ]);
        $this->produto = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Parafuso',
            'tipo' => 1,
            'preco_venda' => 10,
            'estoque_atual' => 5,
            'controla_estoque' => true,
        ]);
        $this->actingAs(User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]));
    }

    private function payload(array $item = [], array $dados = []): array
    {
        return array_merge([
            'acao' => 'rascunho',
            'data_venda' => now()->toDateString(),
            'mod_frete' => 9,
            'itens' => [array_merge([
                'produto_id' => $this->produto->id,
                'quantidade' => '2',
                'preco_unitario' => '10.00',
            ], $item)],
        ], $dados);
    }

    public function test_busca_naturezas_de_operacao_predefinidas(): void
    {
        $todas = $this->getJson(route('api.naturezas-operacao.busca'))
            ->assertOk()
            ->json();

        $this->assertContains('VENDA DE MERCADORIA', array_column($todas, 'nome'));

        $filtradas = $this->getJson(route('api.naturezas-operacao.busca', ['q' => 'combustivel']))
            ->assertOk()
            ->json();

        $this->assertSame(['VENDA DE COMBUSTÍVEL'], array_column($filtradas, 'nome'));
    }

    public function test_nova_nfe_mostra_as_quatro_etapas_e_o_painel_de_pendencias(): void
    {
        $this->get(route('nfe.create'))
            ->assertOk()
            ->assertSee('data-step-btn="4"', false)
            ->assertSee('nfe-pendencias-toggle', false)
            ->assertSee('Destinatário')
            ->assertDontSee('Pendências da empresa para emitir', false);
    }

    public function test_rascunho_com_dados_fiscais_incompletos_e_salvo_sem_estoque_nem_nfe(): void
    {
        $this->post(route('nfe.store'), $this->payload())->assertSessionHasNoErrors();

        $venda = Venda::firstOrFail();
        $this->assertTrue($venda->isRascunhoNfe());
        $this->assertSame(0, Nfe::count());
        $this->assertEquals(5.0, (float) $this->produto->fresh()->estoque_atual);

        $this->get(route('nfe.index'))->assertOk()->assertSee('Rascunho');
        $this->get(route('nfe.rascunho.edit', $venda))
            ->assertOk()
            ->assertSee('Parafuso')
            ->assertSee('id="input-natureza"', false)
            ->assertSee('form="form-excluir-rascunho"', false);
    }

    public function test_rascunho_valida_o_formato_dos_campos_informados(): void
    {
        $this->post(route('nfe.store'), $this->payload(['ncm' => '123']))->assertSessionHasErrors('itens.0.ncm');

        $this->assertSame(0, Venda::count());
    }

    public function test_emitir_exige_os_dados_fiscais(): void
    {
        $this->post(route('nfe.store'), $this->payload([], ['acao' => 'emitir']))
            ->assertSessionHasErrors(['forma_pagamento', 'itens.0.ncm', 'itens.0.cfop']);

        $this->assertSame(0, Venda::count());
    }

    public function test_rascunho_pode_ser_atualizado_e_excluido(): void
    {
        $this->post(route('nfe.store'), $this->payload());
        $venda = Venda::firstOrFail();

        $this->put(route('nfe.rascunho.update', $venda), $this->payload(['quantidade' => '3', 'ncm' => '73181500']))
            ->assertRedirect(route('nfe.rascunho.edit', $venda));

        $item = $venda->fresh('itens')->itens->first();
        $this->assertEquals(3.0, (float) $item->quantidade);
        $this->assertSame('73181500', $item->ncm);
        $this->assertSame(1, Venda::count());

        $this->delete(route('nfe.rascunho.destroy', $venda))->assertRedirect(route('nfe.index'));
        $this->assertSoftDeleted($venda);
    }

    public function test_rascunho_de_outra_empresa_nao_e_acessivel(): void
    {
        $outra = Empresa::create(['razao_social' => 'Outra', 'cnpj' => '11444777000161']);
        $venda = Venda::create([
            'empresa_id' => $outra->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'situacao' => 'em_andamento',
            'nfe_rascunho' => true,
        ]);

        $this->get(route('nfe.rascunho.edit', $venda))->assertNotFound();
        $this->delete(route('nfe.rascunho.destroy', $venda))->assertNotFound();
    }

    public function test_pendencias_listam_todas_as_faltas_de_uma_vez(): void
    {
        $this->post(route('nfe.store'), $this->payload(['cfop' => '6102', 'csosn' => '101']));
        $venda = Venda::firstOrFail();

        $pendencias = app(NfePendenciasService::class)->verificar($venda, $this->empresa);

        $texto = implode(' | ', $pendencias);
        $this->assertStringContainsString('forma de pagamento', $texto);
        $this->assertStringContainsString('NCM', $texto);
        $this->assertStringContainsString('série 5', $texto);
        $this->assertStringContainsString('CSOSN 101', $texto);
    }

    public function test_catalogo_fiscal_devolve_codigo_e_descricao(): void
    {
        $cfop = $this->getJson(route('api.cfops.busca', ['q' => '5102']))
            ->assertOk()
            ->json();

        $this->assertSame('5102 - VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS', $cfop[0]['rotulo']);

        $ncm = $this->getJson(route('api.ncms.busca', ['q' => 'camiseta']))
            ->assertOk()
            ->json();

        $this->assertSame('61091000', $ncm[0]['codigo']);

        $cest = $this->getJson(route('api.cests.busca', ['q' => 'cerveja', 'ncm' => '22030000']))
            ->assertOk()
            ->json();

        $this->assertSame('0300100', $cest[0]['codigo']);
    }

    public function test_rascunho_grava_a_data_de_saida_e_recusa_data_anterior(): void
    {
        $saida = now()->addDay()->toDateString();

        $this->post(route('nfe.store'), $this->payload([], ['data_saida' => $saida]))
            ->assertSessionHasNoErrors();

        $venda = Venda::firstOrFail();
        $this->assertSame($saida, $venda->data_saida->toDateString());
        $this->get(route('nfe.rascunho.edit', $venda))->assertOk()->assertSee($saida, false);

        $this->post(route('nfe.store'), $this->payload([], [
            'data_saida' => now()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('data_saida');
    }

    public function test_data_de_emissao_futura_entra_nas_pendencias(): void
    {
        $this->post(route('nfe.store'), $this->payload([], [
            'data_venda' => now()->addDays(2)->toDateString(),
        ]))->assertSessionHasNoErrors();

        $venda = Venda::firstOrFail();
        $pendencias = app(NfePendenciasService::class)->verificar($venda, $this->empresa);

        $this->assertStringContainsString('posterior a hoje', implode(' | ', $pendencias));
        $this->assertTrue($venda->isRascunhoNfe());
    }

    public function test_tela_mostra_data_de_saida_sem_focar_a_busca_e_o_regime(): void
    {
        $this->get(route('nfe.create'))
            ->assertOk()
            ->assertSee('Data de saída', false)
            ->assertSee('max="'.\Carbon\Carbon::now('America/Sao_Paulo')->toDateString().'"', false)
            ->assertSee('id="lista-cfop"', false)
            ->assertSee('id="lista-ncm"', false)
            ->assertSee('Venda interna no Simples Nacional', false)
            ->assertDontSee('if (editIndex === null) buscaItem.focus()', false);

        $this->empresa->update(['crt' => 4]);
        $this->get(route('nfe.create'))->assertOk()->assertSee('Venda interna no MEI', false);

        $this->empresa->update(['crt' => 3]);
        $this->get(route('nfe.create'))->assertOk()->assertSee('Use Simples Nacional ou MEI', false);
    }
}
