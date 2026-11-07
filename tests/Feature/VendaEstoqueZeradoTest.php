<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Estoque\MovimentacaoEstoque;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendaEstoqueZeradoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa A',
            'cnpj' => '11222333000181',
        ]);
        $this->usuario = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);
        $this->actingAs($this->usuario);
    }

    public function test_pdv_nao_vende_produto_com_estoque_zerado_quando_controla_estoque(): void
    {
        $produto = $this->produto(0, true);

        $resposta = $this->postJson(route('venda.store'), $this->payload($produto, [
            'situacao' => 'confirmada',
        ]));

        $resposta->assertStatus(422);
        $this->assertStringContainsString('não foi concluída', (string) $resposta->json('errors.itens.0'));
        $this->assertStringContainsString('Parafuso', (string) $resposta->json('errors.itens.0'));
        $this->assertSame(0, Venda::count());
        $this->assertSame(0, MovimentacaoEstoque::count());
        $this->assertEquals(0.0, (float) $produto->fresh()->estoque_atual);
    }

    public function test_venda_confirmada_pelo_formulario_nao_baixa_estoque_zerado(): void
    {
        $produto = $this->produto(0, true);

        $this->from(route('venda.create'))
            ->post(route('venda.store'), $this->payload($produto, [
                'situacao' => 'confirmada',
            ]))
            ->assertRedirect(route('venda.create'))
            ->assertSessionHasErrors('itens');

        $this->assertSame(0, Venda::count());
        $this->assertEquals(0.0, (float) $produto->fresh()->estoque_atual);
    }

    public function test_confirmacao_de_rascunho_recusa_estoque_zerado(): void
    {
        $produto = $this->produto(0, true);

        $this->post(route('venda.store'), $this->payload($produto))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $venda = Venda::firstOrFail();
        $this->assertSame('em_andamento', $venda->situacao);
        $this->assertEquals(0.0, (float) $produto->fresh()->estoque_atual);

        $this->post(route('venda.confirmar', $venda))
            ->assertRedirect(route('venda.show', $venda))
            ->assertSessionHas('error');

        $this->assertStringContainsString('não foi concluída', (string) session('error'));
        $this->assertSame('em_andamento', $venda->fresh()->situacao);
        $this->assertEquals(0.0, (float) $produto->fresh()->estoque_atual);
        $this->assertSame(0, MovimentacaoEstoque::count());
    }

    public function test_produto_sem_controle_pode_ser_vendido_com_estoque_zerado(): void
    {
        $produto = $this->produto(0, false);

        $this->postJson(route('venda.store'), $this->payload($produto, [
            'situacao' => 'confirmada',
        ]))->assertCreated();

        $this->assertSame('confirmada', Venda::firstOrFail()->situacao);
        $this->assertEquals(0.0, (float) $produto->fresh()->estoque_atual);
        $this->assertSame(0, MovimentacaoEstoque::count());
    }

    public function test_vende_o_saldo_inteiro_e_recusa_o_que_passar(): void
    {
        $produto = $this->produto(2, true);

        $this->postJson(route('venda.store'), $this->payload($produto, [
            'situacao' => 'confirmada',
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => '2',
                'preco_unitario' => '10,00',
            ]],
        ]))->assertCreated();

        $this->assertEquals(0.0, (float) $produto->fresh()->estoque_atual);

        $this->postJson(route('venda.store'), $this->payload($produto, [
            'situacao' => 'confirmada',
        ]))->assertStatus(422);

        $this->assertSame(1, Venda::count());
        $this->assertEquals(0.0, (float) $produto->fresh()->estoque_atual);
    }

    public function test_editar_e_confirmar_sem_saldo_nao_grava_a_alteracao(): void
    {
        $produto = $this->produto(1, true);
        $venda = Venda::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 10,
            'total' => 10,
            'situacao' => 'em_andamento',
        ]);
        VendaItem::create([
            'venda_id' => $venda->id,
            'produto_id' => $produto->id,
            'produto_nome' => 'Parafuso',
            'quantidade' => 1,
            'preco_unitario' => 10,
            'total' => 10,
        ]);

        $this->put(route('venda.update', $venda), $this->payload($produto, [
            'situacao' => 'confirmada',
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => '5',
                'preco_unitario' => '10,00',
            ]],
        ]))->assertRedirect(route('venda.show', $venda))
            ->assertSessionHas('error');

        $this->assertSame('em_andamento', $venda->fresh()->situacao);
        $this->assertEquals(1.0, (float) $venda->fresh()->itens()->first()->quantidade);
        $this->assertEquals(1.0, (float) $produto->fresh()->estoque_atual);
    }

    public function test_desconto_do_item_maior_que_o_valor_e_recusado(): void
    {
        $produto = $this->produto(5, true);

        $this->post(route('venda.store'), $this->payload($produto, [
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => '1',
                'preco_unitario' => '10,00',
                'desconto' => '10,01',
            ]],
        ]))->assertSessionHasErrors('itens');

        $this->assertSame(0, Venda::count());
    }

    public function test_duas_linhas_do_mesmo_produto_nao_passam_do_saldo(): void
    {
        $produto = $this->produto(1, true);

        $this->postJson(route('venda.store'), $this->payload($produto, [
            'situacao' => 'confirmada',
            'itens' => [
                ['produto_id' => $produto->id, 'quantidade' => '1', 'preco_unitario' => '10,00'],
                ['produto_id' => $produto->id, 'quantidade' => '1', 'preco_unitario' => '10,00'],
            ],
        ]))->assertStatus(422);

        $this->assertSame(0, Venda::count());
        $this->assertEquals(1.0, (float) $produto->fresh()->estoque_atual);
    }

    private function produto(float $estoque, bool $controla): Produto
    {
        return Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Parafuso',
            'tipo' => 1,
            'preco_venda' => 10,
            'estoque_atual' => $estoque,
            'controla_estoque' => $controla,
            'ativo' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Produto $produto, array $extra = []): array
    {
        return array_merge([
            'data_venda' => now()->toDateString(),
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => '1',
                'preco_unitario' => '10,00',
            ]],
        ], $extra);
    }
}
