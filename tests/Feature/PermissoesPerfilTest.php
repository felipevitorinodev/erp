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
}
