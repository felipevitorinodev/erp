<?php

namespace Tests\Feature;

use App\Models\Comissao;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\User;
use App\Models\Venda;
use App\Repositories\VendaRepository;
use App\Services\CategoriaPadraoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfilEFluxosCriticosTest extends TestCase
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

    private function criarUsuario(Empresa $empresa, string $perfil = 'admin'): User
    {
        return User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil'     => $perfil,
            'ativo'      => true,
        ]);
    }

    public function test_operador_nao_acessa_relatorio_contas_receber(): void
    {
        $empresa = $this->criarEmpresa();
        $user = $this->criarUsuario($empresa, 'operador');

        $this->actingAs($user)
            ->get(route('relatorio.contas-receber'))
            ->assertStatus(403);
    }

    public function test_operador_nao_acessa_formas_pagamento(): void
    {
        $empresa = $this->criarEmpresa();
        $user = $this->criarUsuario($empresa, 'operador');

        $this->actingAs($user)
            ->get(route('formaPagamento.index'))
            ->assertStatus(403);
    }

    public function test_financeiro_acessa_fluxo_de_caixa(): void
    {
        $empresa = $this->criarEmpresa();
        $user = $this->criarUsuario($empresa, 'financeiro');

        $this->actingAs($user)
            ->get(route('fluxo-de-caixa.index'))
            ->assertOk();
    }

    public function test_categorias_padrao_sao_criadas_por_empresa(): void
    {
        $empresa = $this->criarEmpresa();
        $service = app(CategoriaPadraoService::class);

        $idReceita = $service->idReceitaVendas($empresa->id);
        $idCompras = $service->idDespesaCompras($empresa->id);
        $idComissoes = $service->idDespesaComissoes($empresa->id);

        $this->assertNotNull($idReceita);
        $this->assertNotNull($idCompras);
        $this->assertNotNull($idComissoes);
        $this->assertNotSame($idReceita, $idCompras);
        $this->assertSame($idReceita, $service->idReceitaVendas($empresa->id));
    }

    public function test_nao_cancela_venda_com_comissao_paga(): void
    {
        $empresa = $this->criarEmpresa();
        $user = $this->criarUsuario($empresa, 'admin');
        $this->actingAs($user);

        $funcionario = Funcionario::create([
            'empresa_id'          => $empresa->id,
            'nome'                => 'Vendedor Teste',
            'percentual_comissao' => 5,
            'ativo'               => true,
        ]);

        $venda = Venda::create([
            'empresa_id'     => $empresa->id,
            'funcionario_id' => $funcionario->id,
            'numero'         => 'V-001',
            'data_venda'     => now()->toDateString(),
            'situacao'       => 'confirmada',
            'subtotal'       => 100,
            'desconto'       => 0,
            'acrescimo'      => 0,
            'total'          => 100,
        ]);

        Comissao::create([
            'empresa_id'     => $empresa->id,
            'venda_id'       => $venda->id,
            'funcionario_id' => $funcionario->id,
            'percentual'     => 5,
            'valor'          => 5,
            'situacao'       => 'paga',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('comissão já paga');

        app(VendaRepository::class)->cancelar($venda);
    }
}
