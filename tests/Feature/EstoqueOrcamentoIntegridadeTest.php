<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Estoque\EntradaEstoque;
use App\Models\Estoque\EntradaEstoqueItem;
use App\Models\Grupos\Grupo;
use App\Models\Orcamentos\Orcamento;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Repositories\Estoque\EntradaEstoqueRepository;
use App\Services\Orcamentos\OrcamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstoqueOrcamentoIntegridadeTest extends TestCase
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
            'estoque_atual' => 0,
            'controla_estoque' => true,
        ]);
        $this->actingAs(User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]));
    }

    public function test_total_do_orcamento_e_recalculado_no_servidor(): void
    {
        $this->post(route('orcamento.store'), [
            'data_orcamento' => now()->toDateString(),
            'subtotal' => '0,01',
            'total' => '0,01',
            'desconto' => '5,00',
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => '2,5', 'preco_unitario' => '10,00', 'desconto' => '0'],
            ],
        ])->assertSessionHasNoErrors();

        $orcamento = Orcamento::with('itens')->firstOrFail();
        $this->assertEquals(25.0, (float) $orcamento->subtotal);
        $this->assertEquals(20.0, (float) $orcamento->total);
        $this->assertEquals(2.5, (float) $orcamento->itens->first()->quantidade);
    }

    public function test_orcamento_aprovado_duas_vezes_gera_uma_venda(): void
    {
        $orcamento = Orcamento::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_orcamento' => now()->toDateString(),
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
        $copiaAntiga = $orcamento->fresh();

        app(OrcamentoService::class)->aprovar($orcamento);
        app(OrcamentoService::class)->aprovar($copiaAntiga);

        $this->assertSame(1, Venda::count());
        $this->assertSame('aprovado', $orcamento->fresh()->situacao);
    }

    public function test_cancelar_entrada_duas_vezes_estorna_estoque_uma_vez(): void
    {
        $entrada = EntradaEstoque::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '123',
            'data_entrada' => now()->toDateString(),
            'total' => 50,
            'situacao' => 'rascunho',
        ]);
        EntradaEstoqueItem::create([
            'entrada_estoque_id' => $entrada->id,
            'produto_id' => $this->produto->id,
            'produto_nome' => 'Produto',
            'quantidade' => 5.125,
            'preco_unitario' => 10,
            'total' => 51.25,
        ]);
        $repositorio = app(EntradaEstoqueRepository::class);
        $repositorio->confirmar($entrada);
        $this->assertEquals(5.125, (float) $this->produto->fresh()->estoque_atual);

        $this->produto->update(['estoque_atual' => 20]);
        $copiaAntiga = $entrada->fresh();
        $repositorio->cancelar($entrada->fresh());

        try {
            $repositorio->cancelar($copiaAntiga);
        } catch (\Exception) {
        }

        $this->assertEquals(14.875, (float) $this->produto->fresh()->estoque_atual);
    }

    public function test_subgrupo_pode_ser_cadastrado(): void
    {
        $pai = Grupo::create(['empresa_id' => $this->empresa->id, 'nome' => 'Pai', 'ativo' => true]);

        $this->post(route('grupo.store'), [
            'empresa_id' => $this->empresa->id,
            'parent_id' => $pai->id,
            'nome' => 'Filho',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('grupos', ['nome' => 'Filho', 'parent_id' => $pai->id]);
    }

    public function test_produto_nao_aceita_grupo_de_outra_empresa(): void
    {
        $outra = Empresa::create(['razao_social' => 'Empresa B', 'cnpj' => '11444777000161']);
        $grupoAlheio = Grupo::create(['empresa_id' => $outra->id, 'nome' => 'Alheio', 'ativo' => true]);

        $this->post(route('produto.store'), [
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $grupoAlheio->id,
            'nome' => 'Produto novo',
            'tipo' => 1,
        ])->assertSessionHasErrors('grupo_id');
    }
}
