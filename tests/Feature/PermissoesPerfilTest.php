<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissoesPerfilTest extends TestCase
{
    use RefreshDatabase;

    private function criarEmpresa(): Empresa
    {
        return Empresa::create([
            'razao_social' => 'Empresa Teste',
            'nome_fantasia' => 'Teste',
            'cnpj' => '11222333000181',
            'ativo' => true,
        ]);
    }

    private function criarUsuario(Empresa $empresa, string $perfil): User
    {
        return User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => $perfil,
            'ativo' => true,
        ]);
    }

    public function test_configuracoes_lista_o_perfil_vendedor_e_permite_criar_outro(): void
    {
        $empresa = $this->criarEmpresa();
        $admin = $this->criarUsuario($empresa, 'admin');

        $this->actingAs($admin)
            ->get(route('permissao.index'))
            ->assertOk()
            ->assertSee('Permissões')
            ->assertSee('Vendedor')
            ->assertSee('Financeiro')
            ->assertSee('Administrador');

        $vendedor = Perfil::where('empresa_id', $empresa->id)->where('slug', 'vendedor')->first();
        $this->assertNotNull($vendedor);
        $this->assertContains('vendas', $vendedor->permissoes);
        $this->assertNotContains('financeiro', $vendedor->permissoes);
        $this->assertNotContains('estoque', $vendedor->permissoes);

        $this->actingAs($admin)
            ->get(route('permissao.edit', $vendedor))
            ->assertOk()
            ->assertSee('Cadastrar cliente rápido no balcão');

        $this->actingAs($admin)
            ->post(route('permissao.store'), [
                'nome' => 'Caixa',
                'ativo' => '1',
                'permissoes' => ['financeiro'],
            ])
            ->assertRedirect(route('permissao.index'));

        $caixa = Perfil::where('empresa_id', $empresa->id)->where('slug', 'caixa')->first();
        $this->assertNotNull($caixa);
        $this->assertSame(['financeiro'], $caixa->permissoes);

        $usuarioCaixa = $this->criarUsuario($empresa, 'caixa');

        $this->actingAs($usuarioCaixa)
            ->get(route('fluxo-de-caixa.index'))
            ->assertOk();

        $this->actingAs($usuarioCaixa)
            ->get(route('produto.index'))
            ->assertStatus(403);

        $this->actingAs($usuarioCaixa)
            ->get(route('permissao.index'))
            ->assertStatus(403);

        $this->actingAs($admin)
            ->delete(route('permissao.destroy', $vendedor))
            ->assertRedirect(route('permissao.index'))
            ->assertSessionHas('error', 'Perfil padrão não pode ser excluído.');

        $this->assertDatabaseHas('perfis', ['id' => $vendedor->id]);
    }

    public function test_vendedor_nao_abre_a_tela_de_permissoes(): void
    {
        $empresa = $this->criarEmpresa();
        $vendedor = $this->criarUsuario($empresa, 'vendedor');

        $this->actingAs($vendedor)
            ->get('/')
            ->assertOk()
            ->assertSee('PDV')
            ->assertDontSee('Permissões');

        $this->actingAs($vendedor)
            ->get(route('permissao.index'))
            ->assertStatus(403);
    }

    public function test_perfil_nao_repete_nome_na_mesma_empresa(): void
    {
        $empresa = $this->criarEmpresa();
        $admin = $this->criarUsuario($empresa, 'admin');

        $this->actingAs($admin)
            ->post(route('permissao.store'), [
                'nome' => 'administrador',
                'ativo' => '1',
                'permissoes' => ['vendas'],
            ])
            ->assertSessionHasErrors('nome');

        $this->assertDatabaseMissing('perfis', [
            'empresa_id' => $empresa->id,
            'slug' => 'administrador',
        ]);

        $this->actingAs($admin)
            ->post(route('permissao.store'), [
                'nome' => 'Caixa',
                'ativo' => '1',
                'permissoes' => ['financeiro'],
            ])
            ->assertRedirect(route('permissao.index'));

        $caixa = Perfil::where('empresa_id', $empresa->id)->where('slug', 'caixa')->first();

        $this->actingAs($admin)
            ->put(route('permissao.update', $caixa), [
                'nome' => 'Caixa',
                'ativo' => '1',
                'permissoes' => ['financeiro'],
            ])
            ->assertRedirect(route('permissao.index'));

        $this->actingAs($admin)
            ->put(route('permissao.update', $caixa), [
                'nome' => 'Vendedor',
                'ativo' => '1',
                'permissoes' => ['financeiro'],
            ])
            ->assertSessionHasErrors('nome');

        $this->assertSame('Caixa', $caixa->fresh()->nome);
    }

    public function test_menu_acompanha_a_permissao_da_rota(): void
    {
        $empresa = $this->criarEmpresa();
        Perfil::create([
            'empresa_id' => $empresa->id,
            'slug' => 'so-formas',
            'nome' => 'Só formas',
            'permissoes' => ['formas_pagamento'],
            'sistema' => false,
            'ativo' => true,
        ]);
        Perfil::create([
            'empresa_id' => $empresa->id,
            'slug' => 'so-relatorios',
            'nome' => 'Só relatórios',
            'permissoes' => ['relatorios'],
            'sistema' => false,
            'ativo' => true,
        ]);
        Perfil::create([
            'empresa_id' => $empresa->id,
            'slug' => 'financeiro-e-formas',
            'nome' => 'Financeiro e formas',
            'permissoes' => ['financeiro', 'formas_pagamento'],
            'sistema' => false,
            'ativo' => true,
        ]);

        $formas = $this->criarUsuario($empresa, 'so-formas');
        $relatorios = $this->criarUsuario($empresa, 'so-relatorios');
        $vendedor = $this->criarUsuario($empresa, 'vendedor');
        $financeiro = $this->criarUsuario($empresa, 'financeiro-e-formas');

        $this->actingAs($formas)
            ->get('/')
            ->assertOk()
            ->assertSee(route('formaPagamento.index'), false)
            ->assertSee('Formas de Pagamento')
            ->assertDontSee(route('conta-receber.index'), false);

        $this->actingAs($formas)
            ->get(route('formaPagamento.index'))
            ->assertOk();

        $this->actingAs($relatorios)
            ->get('/')
            ->assertOk()
            ->assertSee('Posição de estoque')
            ->assertSee(route('relatorio.estoque'), false)
            ->assertDontSee('Posição Atual')
            ->assertDontSee(route('entrada-estoque.index'), false);

        $this->actingAs($relatorios)
            ->get(route('relatorio.estoque'))
            ->assertOk();

        $this->actingAs($vendedor)
            ->get('/')
            ->assertOk()
            ->assertDontSee(route('formaPagamento.index'), false)
            ->assertDontSee(route('relatorio.estoque'), false)
            ->assertDontSee('Posição de estoque')
            ->assertDontSee('Formas de Pagamento');

        $this->actingAs($financeiro)
            ->get('/')
            ->assertOk()
            ->assertSee(route('conta-receber.index'), false)
            ->assertSee(route('conta-pagar.index'), false)
            ->assertSee(route('fluxo-de-caixa.index'), false)
            ->assertSee(route('categoria-financeira.index'), false)
            ->assertSee(route('formaPagamento.index'), false);
    }
}
