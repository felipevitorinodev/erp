<?php

namespace Tests\Feature;

use App\Models\ContasReceber\ContaReceber;
use App\Models\ContasReceber\ContaReceberBaixa;
use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfilVendedorEBaixasTest extends TestCase
{
    use RefreshDatabase;

    private function criarEmpresa(): Empresa
    {
        return Empresa::create([
            'razao_social'  => 'Empresa Teste',
            'nome_fantasia' => 'Teste',
            'cnpj'          => '11222333000181',
            'ativo'         => true,
        ]);
    }

    private function criarUsuario(Empresa $empresa, string $perfil): User
    {
        return User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil'     => $perfil,
            'ativo'      => true,
        ]);
    }

    private function criarVenda(Empresa $empresa, string $situacao, string $numero): Venda
    {
        return Venda::create([
            'empresa_id' => $empresa->id,
            'numero'     => $numero,
            'data_venda' => now()->toDateString(),
            'situacao'   => $situacao,
            'subtotal'   => 10,
            'total'      => 10,
        ]);
    }

    public function test_vendedor_so_ve_vendas_e_nao_acessa_cadastros(): void
    {
        $empresa = $this->criarEmpresa();
        $vendedor = $this->criarUsuario($empresa, 'operador');
        $financeiro = $this->criarUsuario($empresa, 'financeiro');

        $this->criarVenda($empresa, 'confirmada', '000001');
        $this->criarVenda($empresa, 'em_andamento', '000002');
        $cancelada = $this->criarVenda($empresa, 'cancelada', '000003');

        $this->actingAs($vendedor)
            ->get('/')
            ->assertOk()
            ->assertSee('PDV')
            ->assertSee('Nova Venda')
            ->assertDontSee('Entrada de Mercadoria')
            ->assertDontSee('Orçamentos')
            ->assertDontSee('Comissões');

        $this->actingAs($vendedor)
            ->get(route('produto.index'))
            ->assertStatus(403);

        $this->actingAs($vendedor)
            ->get(route('cliente.index'))
            ->assertStatus(403);

        $this->actingAs($vendedor)
            ->post(route('api.grupos.store'), ['nome' => 'Grupo do balcão'])
            ->assertStatus(403);

        $this->actingAs($financeiro)
            ->get(route('produto.index'))
            ->assertOk();

        $this->actingAs($vendedor)
            ->get(route('venda.index'))
            ->assertOk()
            ->assertSee('#000001')
            ->assertSee('#000002')
            ->assertDontSee('#000003')
            ->assertDontSee('Cancelada');

        $this->actingAs($vendedor)
            ->get(route('venda.index', ['situacao' => 'cancelada']))
            ->assertOk()
            ->assertDontSee('#000003');

        $this->actingAs($vendedor)
            ->get(route('venda.show', $cancelada))
            ->assertStatus(403);
    }

    public function test_vendedor_consulta_venda_fechada_e_nao_cancela(): void
    {
        $empresa = $this->criarEmpresa();
        $vendedor = $this->criarUsuario($empresa, 'operador');
        $fechada = $this->criarVenda($empresa, 'confirmada', '000010');
        $aberta = $this->criarVenda($empresa, 'em_andamento', '000011');

        $this->actingAs($vendedor)
            ->get(route('venda.show', $fechada))
            ->assertOk()
            ->assertDontSee('Emitir NF-e')
            ->assertDontSee(route('venda.cancelar', $fechada));

        $this->actingAs($vendedor)
            ->post(route('venda.cancelar', $fechada))
            ->assertRedirect(route('venda.show', $fechada))
            ->assertSessionHas('error', 'Venda fechada só pode ser consultada.');

        $this->assertSame('confirmada', $fechada->fresh()->situacao);

        $this->actingAs($vendedor)
            ->get(route('venda.show', $aberta))
            ->assertOk()
            ->assertSee(route('venda.cancelar', $aberta));
    }

    public function test_recebimentos_parciais_entram_no_fluxo_no_dia_de_cada_baixa(): void
    {
        $empresa = $this->criarEmpresa();
        $user = $this->criarUsuario($empresa, 'financeiro');

        $conta = ContaReceber::create([
            'empresa_id'      => $empresa->id,
            'descricao'       => 'Parcela do cliente',
            'valor'           => 100,
            'valor_pago'      => 0,
            'data_vencimento' => '2026-10-20',
            'situacao'        => 'aberta',
        ]);

        $this->actingAs($user)
            ->post(route('conta-receber.receber', $conta), [
                'valor_pago'      => '50,00',
                'data_pagamento'  => '2026-10-01',
                'forma_pagamento' => 'Dinheiro',
            ])
            ->assertRedirect(route('conta-receber.show', $conta));

        $this->actingAs($user)
            ->post(route('conta-receber.receber', $conta), [
                'valor_pago'      => '50,00',
                'data_pagamento'  => '2026-10-02',
                'forma_pagamento' => 'Pix',
            ])
            ->assertRedirect(route('conta-receber.show', $conta));

        $this->assertSame('paga', $conta->fresh()->situacao);
        $this->assertEquals(2, ContaReceberBaixa::where('conta_receber_id', $conta->id)->count());

        $this->actingAs($user)
            ->get(route('fluxo-de-caixa.index', [
                'data_inicio' => '2026-10-01',
                'data_fim'    => '2026-10-02',
            ]))
            ->assertOk()
            ->assertSeeInOrder([
                '01/10/2026',
                'R$ 50,00',
                '02/10/2026',
                'R$ 50,00',
            ]);

        $this->actingAs($user)
            ->get(route('conta-receber.show', $conta))
            ->assertOk()
            ->assertSee('Recebimentos')
            ->assertSee('01/10/2026')
            ->assertSee('02/10/2026');
    }

    public function test_conta_parcial_nao_cancela_e_nao_aceita_valor_menor_que_o_recebido(): void
    {
        $empresa = $this->criarEmpresa();
        $user = $this->criarUsuario($empresa, 'financeiro');

        $conta = ContaReceber::create([
            'empresa_id'      => $empresa->id,
            'descricao'       => 'Conta parcial',
            'valor'           => 100,
            'valor_pago'      => 0,
            'data_vencimento' => '2026-10-20',
            'situacao'        => 'aberta',
        ]);

        $this->actingAs($user)
            ->post(route('conta-receber.receber', $conta), [
                'valor_pago'     => '40,00',
                'data_pagamento' => '2026-10-01',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('conta-receber.show', $conta))
            ->assertOk()
            ->assertSee('Registrar Recebimento')
            ->assertDontSee('Cancelar conta');

        $this->actingAs($user)
            ->post(route('conta-receber.cancelar', $conta))
            ->assertRedirect(route('conta-receber.show', $conta))
            ->assertSessionHas('error', 'Conta com recebimento não pode ser cancelada.');

        $this->assertSame('parcial', $conta->fresh()->situacao);

        $this->actingAs($user)
            ->put(route('conta-receber.update', $conta), [
                'descricao'       => 'Conta parcial',
                'valor'           => '10,00',
                'data_vencimento' => '2026-10-20',
            ])
            ->assertSessionHasErrors('valor');

        $this->assertEquals(100.0, (float) $conta->fresh()->valor);

        $this->actingAs($user)
            ->put(route('conta-receber.update', $conta), [
                'descricao'       => 'Conta parcial',
                'valor'           => '40,00',
                'data_vencimento' => '2026-10-20',
            ])
            ->assertRedirect(route('conta-receber.show', $conta));

        $this->assertSame('paga', $conta->fresh()->situacao);
    }
}
