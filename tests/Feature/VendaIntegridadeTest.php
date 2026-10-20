<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Nfe;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use App\Repositories\Vendas\VendaRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendaIntegridadeTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $usuario;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['razao_social' => 'Empresa A', 'cnpj' => '11222333000181']);
        $this->usuario = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);
        $this->produto = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Produto',
            'tipo' => 1,
            'preco_venda' => 10,
            'estoque_atual' => 8,
            'controla_estoque' => true,
        ]);
        $this->actingAs($this->usuario);
    }

    private function vendaConfirmada(): Venda
    {
        $venda = Venda::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 20,
            'total' => 20,
            'situacao' => 'confirmada',
        ]);
        VendaItem::create([
            'venda_id' => $venda->id,
            'produto_id' => $this->produto->id,
            'produto_nome' => 'Produto',
            'quantidade' => 2,
            'preco_unitario' => 10,
            'total' => 20,
        ]);

        return $venda;
    }

    public function test_cancelar_duas_vezes_estorna_estoque_uma_vez(): void
    {
        $venda = $this->vendaConfirmada();
        $repositorio = app(VendaRepository::class);

        $repositorio->cancelar($venda);

        try {
            $repositorio->cancelar($venda);
        } catch (\Exception) {
        }

        $this->assertEquals(10.0, (float) $this->produto->fresh()->estoque_atual);
        $this->assertSame('cancelada', $venda->fresh()->situacao);
    }

    public function test_venda_com_nfe_autorizada_nao_pode_ser_cancelada(): void
    {
        $venda = $this->vendaConfirmada();
        Nfe::create([
            'empresa_id' => $this->empresa->id,
            'venda_id' => $venda->id,
            'numero' => '000000001',
            'serie' => '001',
            'situacao' => 'autorizada',
            'ambiente' => 2,
        ]);

        $this->post(route('venda.cancelar', $venda))->assertSessionHas('error');

        $this->assertSame('confirmada', $venda->fresh()->situacao);
        $this->assertEquals(8.0, (float) $this->produto->fresh()->estoque_atual);
    }

    public function test_numero_da_venda_nao_reutiliza_numero_de_venda_excluida(): void
    {
        Venda::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000009',
            'data_venda' => now()->toDateString(),
            'situacao' => 'em_andamento',
        ])->delete();

        $this->assertSame('000010', app(VendaRepository::class)->proximoNumero($this->empresa->id));
    }

    public function test_desconto_maior_que_subtotal_e_recusado(): void
    {
        $this->post(route('venda.store'), [
            'data_venda' => now()->toDateString(),
            'desconto' => '50,00',
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => '1', 'preco_unitario' => '10,00'],
            ],
        ])->assertSessionHasErrors('desconto');

        $this->assertSame(0, Venda::count());
    }
}
