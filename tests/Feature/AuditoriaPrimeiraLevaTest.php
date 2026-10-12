<?php

namespace Tests\Feature;

use App\Http\Requests\Vendas\VendaRequest;
use App\Models\Clientes\Cliente;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\Estoque\EntradaEstoque;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Fornecedores\Fornecedor;
use App\Models\Nfe;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Services\Vendas\VendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriaPrimeiraLevaTest extends TestCase
{
    use RefreshDatabase;

    private function criarEmpresa(string $nome, string $cnpj): Empresa
    {
        return Empresa::create([
            'razao_social'  => $nome,
            'nome_fantasia' => $nome,
            'cnpj'          => $cnpj,
            'ativo'         => true,
        ]);
    }

    private function criarUsuario(Empresa $empresa, string $perfil = 'admin'): User
    {
        return User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil'     => $perfil,
            'ativo'      => true,
        ]);
    }

    public function test_update_de_cliente_nao_troca_empresa(): void
    {
        $empresaA = $this->criarEmpresa('Empresa A', '00000000000191');
        $empresaB = $this->criarEmpresa('Empresa B', '00000000000272');
        $user = $this->criarUsuario($empresaA);

        $cliente = Cliente::create([
            'empresa_id' => $empresaA->id,
            'nome'       => 'Cliente Original',
            'ativo'      => true,
        ]);

        $this->actingAs($user)
            ->put(route('cliente.update', $cliente), [
                'empresa_id'  => $empresaB->id,
                'nome'        => 'Cliente Editado',
                'tipo_pessoa' => 'PF',
                'ativo'       => 1,
            ])
            ->assertRedirect(route('cliente.index'));

        $cliente->refresh();
        $this->assertSame($empresaA->id, $cliente->empresa_id);
        $this->assertSame('Cliente Editado', $cliente->nome);
    }

    public function test_forma_de_pagamento_nasce_na_empresa_da_sessao(): void
    {
        $empresaA = $this->criarEmpresa('Empresa A', '00000000000191');
        $empresaB = $this->criarEmpresa('Empresa B', '00000000000272');
        $user = $this->criarUsuario($empresaA);

        $this->actingAs($user)
            ->post(route('formaPagamento.store'), [
                'empresa_id' => $empresaB->id,
                'nome'       => 'Dinheiro',
                'tipo'       => 'avista',
                'ativo'      => 1,
            ])
            ->assertRedirect(route('formaPagamento.index'));

        $this->assertDatabaseHas('formas_pagamento', [
            'nome'       => 'Dinheiro',
            'empresa_id' => $empresaA->id,
        ]);
        $this->assertDatabaseMissing('formas_pagamento', [
            'nome'       => 'Dinheiro',
            'empresa_id' => $empresaB->id,
        ]);
    }

    public function test_admin_so_acessa_a_empresa_vinculada(): void
    {
        $empresaA = $this->criarEmpresa('Empresa Vinculada', '00000000000191');
        $empresaB = $this->criarEmpresa('Empresa Alheia', '00000000000272');
        $user = $this->criarUsuario($empresaA);

        $this->actingAs($user)
            ->get(route('empresa.index'))
            ->assertOk()
            ->assertSee('Empresa Vinculada')
            ->assertDontSee('Empresa Alheia')
            ->assertDontSee('Nova Empresa');

        $this->actingAs($user)
            ->get(route('empresa.edit', $empresaB))
            ->assertNotFound();
    }

    public function test_dashboard_soma_saldo_de_conta_parcial(): void
    {
        $empresa = $this->criarEmpresa('Empresa A', '00000000000191');
        $user = $this->criarUsuario($empresa);

        ContaReceber::create([
            'empresa_id'      => $empresa->id,
            'descricao'       => 'Parcial da semana',
            'valor'           => 100,
            'valor_pago'      => 40,
            'data_vencimento' => now()->addDays(3)->toDateString(),
            'situacao'        => 'parcial',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('R$ 60,00')
            ->assertDontSee('R$ 100,00');
    }

    public function test_venda_a_vista_entra_no_fluxo_de_caixa(): void
    {
        $empresa = $this->criarEmpresa('Empresa A', '00000000000191');
        $user = $this->criarUsuario($empresa);

        FormaPagamento::create([
            'empresa_id'         => $empresa->id,
            'nome'               => 'Dinheiro',
            'tipo'               => 'avista',
            'gera_conta_receber' => false,
            'ativo'              => true,
        ]);
        FormaPagamento::create([
            'empresa_id'         => $empresa->id,
            'nome'               => 'Boleto',
            'tipo'               => 'prazo',
            'gera_conta_receber' => true,
            'dias_vencimento'    => 30,
            'ativo'              => true,
        ]);

        Venda::create([
            'empresa_id'      => $empresa->id,
            'numero'          => '1',
            'data_venda'      => now()->toDateString(),
            'situacao'        => 'confirmada',
            'subtotal'        => 150,
            'total'           => 150,
            'forma_pagamento' => 'Dinheiro',
        ]);
        Venda::create([
            'empresa_id'      => $empresa->id,
            'numero'          => '2',
            'data_venda'      => now()->toDateString(),
            'situacao'        => 'confirmada',
            'subtotal'        => 999,
            'total'           => 999,
            'forma_pagamento' => 'Boleto',
        ]);

        $this->actingAs($user)
            ->get(route('fluxo-de-caixa.index'))
            ->assertOk()
            ->assertSee('R$ 150,00')
            ->assertDontSee('R$ 999,00');
    }

    public function test_venda_confirmada_nao_pode_ser_editada(): void
    {
        $empresa = $this->criarEmpresa('Empresa A', '00000000000191');
        $user = $this->criarUsuario($empresa);

        $venda = Venda::create([
            'empresa_id' => $empresa->id,
            'numero'     => '10',
            'data_venda' => now()->toDateString(),
            'situacao'   => 'confirmada',
            'subtotal'   => 100,
            'total'      => 100,
        ]);

        $this->actingAs($user)
            ->get(route('venda.index'))
            ->assertOk()
            ->assertDontSee(route('venda.edit', $venda));

        $this->actingAs($user)
            ->get(route('venda.edit', $venda))
            ->assertRedirect(route('venda.show', $venda))
            ->assertSessionHas('error');

        $request = VendaRequest::create('/venda/'.$venda->id.'/editar/salvar', 'PUT', [
            'total' => 1,
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));

        app(VendaService::class)->update($request, $venda->fresh());

        $this->assertSame('100.00', $venda->fresh()->total);
    }

    public function test_nfe_cancelada_permite_nova_emissao(): void
    {
        $empresa = $this->criarEmpresa('Empresa A', '00000000000191');
        $user = $this->criarUsuario($empresa);

        $venda = Venda::create([
            'empresa_id' => $empresa->id,
            'numero'     => '20',
            'data_venda' => now()->toDateString(),
            'situacao'   => 'confirmada',
            'subtotal'   => 50,
            'total'      => 50,
        ]);

        Nfe::create([
            'empresa_id' => $empresa->id,
            'venda_id'   => $venda->id,
            'numero'     => '000000001',
            'serie'      => '001',
            'situacao'   => 'cancelada',
            'ambiente'   => 2,
        ]);

        $this->actingAs($user)
            ->get(route('venda.show', $venda))
            ->assertOk()
            ->assertSee('Emitir nova NF-e');

        $response = $this->actingAs($user)->post(route('nfe.emitir', $venda));
        $response->assertRedirect();

        $this->assertSame(2, Nfe::where('venda_id', $venda->id)->count());
        $this->assertStringNotContainsString('cancelada', (string) session('error'));
    }

    public function test_rascunho_de_entrada_nao_altera_custo_e_confirmacao_cria_produto_sem_margem(): void
    {
        $empresa = $this->criarEmpresa('Empresa A', '00000000000191');
        $user = $this->criarUsuario($empresa);

        $fornecedor = Fornecedor::create([
            'empresa_id' => $empresa->id,
            'nome'       => 'Fornecedor Teste',
            'ativo'      => true,
        ]);

        $existente = Produto::create([
            'empresa_id'       => $empresa->id,
            'nome'             => 'Produto Existente',
            'codigo'           => 'EXIST01',
            'preco_custo'      => 5,
            'preco_venda'      => 20,
            'estoque_atual'    => 3,
            'controla_estoque' => true,
            'tipo'             => 1,
            'ativo'            => true,
        ]);

        $this->actingAs($user)
            ->post(route('entrada-estoque.store'), [
                'fornecedor_id' => $fornecedor->id,
                'numero'        => 'NF-1',
                'data_entrada'  => now()->toDateString(),
                'itens'         => [
                    [
                        'produto_id'     => $existente->id,
                        'quantidade'     => '2',
                        'preco_unitario' => '10',
                    ],
                    [
                        'produto_nome'   => 'Produto da Nota',
                        'produto_codigo' => 'XML01',
                        'quantidade'     => '1',
                        'preco_unitario' => '8',
                    ],
                ],
            ])
            ->assertRedirect();

        $existente->refresh();
        $this->assertEquals(5.0, (float) $existente->preco_custo);
        $this->assertEquals(3.0, (float) $existente->estoque_atual);
        $this->assertNull(Produto::where('codigo', 'XML01')->first());

        $entrada = EntradaEstoque::where('numero', 'NF-1')->firstOrFail();
        $this->assertNull($entrada->itens()->where('produto_codigo', 'XML01')->value('produto_id'));

        $this->actingAs($user)
            ->post(route('entrada-estoque.confirmar', $entrada))
            ->assertRedirect();

        $existente->refresh();
        $this->assertEquals(10.0, (float) $existente->preco_custo);
        $this->assertEquals(5.0, (float) $existente->estoque_atual);

        $novo = Produto::where('codigo', 'XML01')->first();
        $this->assertNotNull($novo);
        $this->assertEquals(0.0, (float) $novo->preco_venda);
        $this->assertEquals(8.0, (float) $novo->preco_custo);
        $this->assertEquals(1.0, (float) $novo->estoque_atual);
    }
}
