<?php

namespace Tests\Feature;

use App\Models\ContasPagar\ContaPagar;
use App\Models\Empresas\Empresa;
use App\Models\Estoque\EntradaEstoque;
use App\Models\Estoque\PedidoCompra;
use App\Models\Fornecedores\Fornecedor;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoCompraTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Fornecedor $fornecedor;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['razao_social' => 'Empresa A', 'cnpj' => '11222333000181']);
        $this->fornecedor = Fornecedor::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Fornecedor Teste',
            'ativo' => true,
        ]);
        $this->produto = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Produto',
            'codigo' => 'P1',
            'tipo' => 1,
            'preco_custo' => 3,
            'preco_venda' => 10,
            'estoque_atual' => 3,
            'controla_estoque' => true,
            'ativo' => true,
        ]);

        $this->actingAs(User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]));
    }

    public function test_aprovacao_recebimentos_parciais_e_estorno_mantem_pedido_estoque_e_contas_integrados(): void
    {
        $pedido = $this->criarPedido('5.125');

        $this->post(route('pedido-compra.aprovar', $pedido))
            ->assertSessionHas('success');

        $this->assertSame('aprovado', $pedido->fresh()->situacao);
        $this->assertSame(0, ContaPagar::count());

        $this->get(route('entrada-estoque.create', ['pedido_compra_id' => $pedido->id]))
            ->assertOk()
            ->assertSee($pedido->numero)
            ->assertSee('5,125');

        $primeiraEntrada = $this->criarEntrada($pedido, 'NF-1', '2.125');
        $this->confirmarEntrada($primeiraEntrada);

        $this->assertSame('parcial', $pedido->fresh()->situacao);
        $this->assertEquals(2.125, (float) $pedido->itens()->first()->fresh()->quantidade_recebida);
        $this->assertEquals(5.125, (float) $this->produto->fresh()->estoque_atual);
        $this->assertEquals(4.5, (float) $this->produto->fresh()->preco_custo);
        $this->assertEquals(9.56, (float) ContaPagar::where('entrada_estoque_id', $primeiraEntrada->id)->value('valor'));

        $segundaEntrada = $this->criarEntrada($pedido, 'NF-2', '3');
        $this->confirmarEntrada($segundaEntrada);

        $this->assertSame('recebido', $pedido->fresh()->situacao);
        $this->assertEquals(8.125, (float) $this->produto->fresh()->estoque_atual);
        $this->assertSame(2, ContaPagar::count());

        $this->post(route('entrada-estoque.cancelar', $primeiraEntrada))
            ->assertSessionHas('success');

        $this->assertSame('parcial', $pedido->fresh()->situacao);
        $this->assertEquals(3.0, (float) $pedido->itens()->first()->fresh()->quantidade_recebida);
        $this->assertEquals(6.0, (float) $this->produto->fresh()->estoque_atual);
        $this->assertSame('cancelada', ContaPagar::where('entrada_estoque_id', $primeiraEntrada->id)->value('situacao'));
    }

    public function test_recebimento_acima_do_saldo_do_pedido_nao_movimenta_estoque_nem_gera_conta(): void
    {
        $pedido = $this->criarPedido('2');
        $this->post(route('pedido-compra.aprovar', $pedido));

        $entrada = $this->criarEntrada($pedido, 'NF-EXCESSO', '3');

        $this->post(route('entrada-estoque.confirmar', $entrada))
            ->assertSessionHas('error', fn (string $mensagem) => str_contains($mensagem, 'excede o saldo pendente'));

        $this->assertSame('rascunho', $entrada->fresh()->situacao);
        $this->assertSame('aprovado', $pedido->fresh()->situacao);
        $this->assertEquals(3.0, (float) $this->produto->fresh()->estoque_atual);
        $this->assertSame(0, ContaPagar::count());
    }

    public function test_pedido_cancelado_nao_aceita_novo_recebimento(): void
    {
        $pedido = $this->criarPedido('2');
        $this->post(route('pedido-compra.cancelar', $pedido))
            ->assertSessionHas('success');

        $this->post(route('entrada-estoque.store'), [
            'fornecedor_id' => $this->fornecedor->id,
            'pedido_compra_id' => $pedido->id,
            'numero' => 'NF-CANCELADO',
            'data_entrada' => now()->toDateString(),
            'itens' => [[
                'produto_id' => $this->produto->id,
                'quantidade' => '1',
                'preco_unitario' => '4,50',
                'desconto' => '0,00',
            ]],
        ])->assertSessionHasErrors('pedido_compra_id');

        $this->assertSame('cancelado', $pedido->fresh()->situacao);
        $this->assertSame(0, EntradaEstoque::count());
        $this->assertEquals(3.0, (float) $this->produto->fresh()->estoque_atual);
        $this->assertSame(0, ContaPagar::count());
    }

    public function test_recebimento_lista_so_itens_pendentes_com_indices_sem_buraco(): void
    {
        $outro = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Segundo produto',
            'codigo' => 'P2',
            'tipo' => 1,
            'preco_venda' => 8,
            'estoque_atual' => 0,
            'controla_estoque' => true,
            'ativo' => true,
        ]);
        $this->post(route('pedido-compra.store'), [
            'fornecedor_id' => $this->fornecedor->id,
            'data_emissao' => now()->toDateString(),
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => '2', 'preco_unitario' => '4,00', 'desconto' => '0,00'],
                ['produto_id' => $outro->id, 'quantidade' => '5', 'preco_unitario' => '3,00', 'desconto' => '0,00'],
            ],
        ])->assertSessionHasNoErrors();
        $pedido = PedidoCompra::firstOrFail();
        $this->post(route('pedido-compra.aprovar', $pedido));
        $this->confirmarEntrada($this->criarEntrada($pedido, 'NF-1', '2'));

        // O primeiro item já chegou inteiro: o segundo vira o item 0, e o próximo item novo usa o índice 1.
        $this->get(route('entrada-estoque.create', ['pedido_compra_id' => $pedido->id]))
            ->assertOk()
            ->assertSee('name="itens[0][produto_id]"', false)
            ->assertDontSee('name="itens[1][produto_id]"', false)
            ->assertSee('var indice = 1;', false)
            ->assertSee('Segundo produto');
    }

    public function test_erro_de_validacao_devolve_itens_com_nome_e_mensagem_do_item(): void
    {
        $this->from(route('pedido-compra.create'))->post(route('pedido-compra.store'), [
            'fornecedor_id' => $this->fornecedor->id,
            'data_emissao' => now()->toDateString(),
            'itens' => [
                5 => [
                    'produto_id' => $this->produto->id,
                    'produto_nome' => 'Produto',
                    'quantidade' => '1,000',
                    'preco_unitario' => '1.234,00',
                    'desconto' => '9.999,00',
                ],
            ],
        ])->assertSessionHasErrors('itens.5.desconto');

        // A mensagem por item é conferida no navegador: com a sessão em JSON, o teste não repassa
        // o saco de erros para a requisição seguinte (o _old_input passa).
        $this->get(route('pedido-compra.create'))
            ->assertOk()
            ->assertSee('name="itens[0][produto_id]"', false)
            ->assertSee('value="Produto"', false)
            ->assertSee('value="1,000"', false)
            ->assertSee('value="1.234,00"', false)
            ->assertSee('value="9.999,00"', false)
            ->assertSee('var indice = 1;', false);
        $this->assertSame(0, PedidoCompra::count());
    }

    private function criarPedido(string $quantidade): PedidoCompra
    {
        $this->post(route('pedido-compra.store'), [
            'fornecedor_id' => $this->fornecedor->id,
            'data_emissao' => now()->toDateString(),
            'data_prevista' => now()->addDays(7)->toDateString(),
            'desconto' => '0,00',
            'itens' => [[
                'produto_id' => $this->produto->id,
                'quantidade' => $quantidade,
                'preco_unitario' => '4,00',
                'desconto' => '0,00',
            ]],
        ])->assertSessionHasNoErrors();

        return PedidoCompra::with('itens')->firstOrFail();
    }

    private function criarEntrada(PedidoCompra $pedido, string $numero, string $quantidade): EntradaEstoque
    {
        $this->post(route('entrada-estoque.store'), [
            'fornecedor_id' => $this->fornecedor->id,
            'pedido_compra_id' => $pedido->id,
            'numero' => $numero,
            'data_entrada' => now()->toDateString(),
            'itens' => [[
                'produto_id' => $this->produto->id,
                'quantidade' => $quantidade,
                'preco_unitario' => '4,50',
                'desconto' => '0,00',
            ]],
        ])->assertSessionHasNoErrors();

        return EntradaEstoque::where('numero', $numero)->firstOrFail();
    }

    private function confirmarEntrada(EntradaEstoque $entrada): void
    {
        $this->post(route('entrada-estoque.confirmar', $entrada))
            ->assertSessionHas('success');
    }
}
