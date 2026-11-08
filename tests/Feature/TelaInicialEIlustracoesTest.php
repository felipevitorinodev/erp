<?php

namespace Tests\Feature;

use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelaInicialEIlustracoesTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['razao_social' => 'Loja Nova', 'nome_fantasia' => 'Loja Nova', 'cnpj' => '11222333000181', 'ativo' => true]);
        $this->admin = User::factory()->create(['empresa_id' => $this->empresa->id, 'perfil' => 'admin', 'ativo' => true, 'name' => 'Rafael Nunes']);
    }

    public function test_saudacao_e_ilustracao_seguem_o_periodo_do_dia(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $this->actingAs($this->admin)->get('/')->assertOk()
            ->assertSee('Bom dia, <strong>Rafael</strong>', false)
            ->assertSee('ilustracao--manha', false);

        foreach ([[4, 59, 'Boa noite', 'noite'], [5, 0, 'Bom dia', 'manha'], [12, 0, 'Boa tarde', 'tarde'], [20, 30, 'Boa noite', 'noite'], [1, 20, 'Boa noite', 'noite']] as [$h, $m, $saudacao, $periodo]) {
            $this->travelTo(now()->setTime($h, $m));
            $this->actingAs($this->admin)->get('/')->assertOk()
                ->assertSee($saudacao.', <strong>Rafael</strong>', false)
                ->assertSee('ilustracao--'.$periodo, false);
        }
    }

    public function test_barra_do_assistente_so_para_quem_tem_o_assistente(): void
    {
        $this->actingAs($this->admin)->get('/')
            ->assertSee('Peça ao assistente')
            ->assertSee('Ex.: “a Maria comprou 2 coca no pix”');

        $vendedor = User::factory()->create(['empresa_id' => $this->empresa->id, 'perfil' => 'operador', 'ativo' => true]);
        $this->actingAs($vendedor)->get('/')->assertOk()->assertDontSee('Peça ao assistente');
    }

    public function test_primeiros_passos_somem_quando_a_empresa_esta_pronta(): void
    {
        $this->actingAs($this->admin)->get('/')
            ->assertSee('Primeiros passos')
            ->assertSee('0 de 4 concluídos')
            ->assertSee('Tudo em dia');

        Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Café', 'tipo' => 1, 'preco_venda' => 5, 'ativo' => true]);
        FormaPagamento::create(['empresa_id' => $this->empresa->id, 'nome' => 'Pix', 'tipo' => 'avista', 'gera_conta_receber' => false, 'ativo' => true]);
        $this->actingAs($this->admin)->get('/')->assertSee('2 de 4 concluídos');

        Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Maria', 'ativo' => true]);
        Venda::create(['empresa_id' => $this->empresa->id, 'numero' => '000001', 'data_venda' => now()->toDateString(), 'situacao' => 'confirmada', 'subtotal' => 5, 'total' => 5]);
        $this->actingAs($this->admin)->get('/')->assertDontSee('Primeiros passos');
    }

    public function test_ilustracoes_nas_telas_de_uso_diario(): void
    {
        $this->get(route('login'))->assertSee('ilustracao--loja', false);

        $this->actingAs($this->admin);
        $this->get(route('venda.create'))->assertOk()->assertSee('ilustracao--carrinho', false);
        $this->get(route('pdv.index'))->assertOk()->assertSee('ilustracao--carrinho', false);
        $this->get(route('orcamento.create'))->assertOk()->assertSee('ilustracao--prancheta', false);
        $this->get(route('entrada-estoque.index'))->assertOk()->assertSee('ilustracao--xml', false);
    }

    public function test_cada_listagem_vazia_tem_desenho_proprio(): void
    {
        $this->actingAs($this->admin);

        foreach ([
            'cliente.index' => 'pessoas',
            'fornecedor.index' => 'caminhao',
            'produto.index' => 'prateleira',
            'venda.index' => 'carrinho',
            'conta-pagar.index' => 'boleto',
            'ordem-servico.index' => 'ferramentas',
        ] as $rota => $ilustracao) {
            $this->get(route($rota))->assertOk()->assertSee('ilustracao--'.$ilustracao, false);
        }
    }
}
