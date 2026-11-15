<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Componentes da revisão visual: abas de situação nas listagens (x-filtro-abas + x-filtros :manter)
 * e busca rápida do cabeçalho (x-busca-rapida), que só lista ações permitidas ao perfil.
 */
class ListagemAbasEBuscaRapidaTest extends TestCase
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

    public function test_abas_de_situacao_mantem_os_outros_filtros_e_marcam_a_ativa(): void
    {
        $resposta = $this->actingAs($this->usuario('admin'))
            ->get(route('venda.index', ['busca' => 'maria', 'situacao' => 'confirmada', 'page' => 3]));

        $resposta->assertOk();
        $html = $resposta->getContent();

        // aba ativa marcada; links trocam só a situação, mantêm a busca e voltam para a página 1
        $this->assertMatchesRegularExpression('/class="abas-filtro__item is-ativa"[^>]*aria-current="true"\s*>Confirmadas</', $html);
        $this->assertStringContainsString(e(route('venda.index', ['busca' => 'maria', 'situacao' => 'em_andamento'])), $html);
        $this->assertStringContainsString('href="'.e(route('venda.index', ['busca' => 'maria'])).'"', $html);
        $this->assertStringNotContainsString('page=3&amp;situacao', $html);

        // o formulário de filtros leva a situação escolhida na aba, sem repetir o campo visível
        $this->assertStringContainsString('<input type="hidden" name="situacao" value="confirmada">', $html);
        $this->assertStringNotContainsString('id="campo-situacao"', $html);
    }

    public function test_situacao_fora_da_lista_nao_marca_nenhuma_aba_como_ativa(): void
    {
        $html = $this->actingAs($this->usuario('admin'))
            ->get(route('conta-pagar.index', ['situacao' => 'inexistente']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('abas-filtro__item is-ativa', $html);
    }

    public function test_listagem_sem_situacao_marca_todas(): void
    {
        $html = $this->actingAs($this->usuario('admin'))
            ->get(route('ordem-servico.index'))
            ->assertOk()
            ->assertSee('Aguardando peça')
            ->getContent();

        $this->assertMatchesRegularExpression('/class="abas-filtro__item is-ativa"\s*aria-current="true"\s*>Todas</', $html);
    }

    public function test_busca_rapida_lista_so_acoes_permitidas_ao_perfil(): void
    {
        $admin = $this->actingAs($this->usuario('admin'))->get(route('venda.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="busca-rapida"', $admin);
        $this->assertStringContainsString('data-busca-abrir', $admin);
        $this->assertStringContainsString('Nova conta a pagar', $admin);

        // vendedor (padrão: vendas e cliente de balcão) não vê ações de financeiro nem de cadastros
        $vendedor = $this->actingAs($this->usuario('vendedor'))->get(route('venda.index'))->assertOk()->getContent();
        preg_match('#<script type="application/json" id="busca-rapida-acoes">(.*?)</script>#s', $vendedor, $m);
        $acoes = collect(json_decode($m[1] ?? '[]', true))->pluck('rotulo');

        $this->assertContains('Nova venda', $acoes);
        $this->assertNotContains('Nova conta a pagar', $acoes);
        $this->assertNotContains('Novo produto', $acoes);
        $this->assertNotContains('Novo usuário', $acoes);
    }
}
