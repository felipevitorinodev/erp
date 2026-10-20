<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuariosSegurancaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['razao_social' => 'Empresa A', 'cnpj' => '11222333000181']);
        Perfil::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'slug' => 'gestor'],
            ['nome' => 'Gestor', 'permissoes' => ['configuracoes'], 'sistema' => false, 'ativo' => true],
        );
        $this->admin = $this->usuario('admin');
    }

    private function usuario(string $perfil, array $dados = []): User
    {
        return User::factory()->create(array_merge([
            'empresa_id' => $this->empresa->id,
            'perfil' => $perfil,
            'ativo' => true,
        ], $dados));
    }

    public function test_login_bloqueia_apos_cinco_tentativas(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.post'), ['email' => $this->admin->email, 'password' => 'errada']);
        }

        $this->post(route('login.post'), ['email' => $this->admin->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_nao_admin_nao_se_promove_a_admin(): void
    {
        $gestor = $this->usuario('gestor');

        $this->actingAs($gestor)->put(route('usuario.update', $gestor), [
            'name' => $gestor->name,
            'email' => $gestor->email,
            'perfil' => 'admin',
            'ativo' => 1,
        ])->assertSessionHasErrors('perfil');

        $this->assertSame('gestor', $gestor->fresh()->perfil);
    }

    public function test_usuario_nao_admin_nao_altera_senha_de_admin(): void
    {
        $gestor = $this->usuario('gestor');
        $hashAntes = $this->admin->password;

        $this->actingAs($gestor)->put(route('usuario.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => 'tomado@exemplo.com',
            'password' => 'novasenha',
            'password_confirmation' => 'novasenha',
            'perfil' => 'admin',
            'ativo' => 1,
        ])->assertSessionHasErrors('perfil');

        $this->assertSame($hashAntes, $this->admin->fresh()->password);
    }

    public function test_ultimo_admin_nao_pode_ser_rebaixado(): void
    {
        $this->actingAs($this->admin)->put(route('usuario.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'perfil' => 'vendedor',
            'ativo' => 1,
        ]);

        $this->assertSame('admin', $this->admin->fresh()->perfil);
    }

    public function test_perfil_inativo_perde_permissoes(): void
    {
        $gestor = $this->usuario('gestor');
        Perfil::where('slug', 'gestor')->update(['ativo' => false]);

        $this->assertFalse($gestor->fresh()->pode('configuracoes'));
    }

    public function test_esqueci_senha_nao_revela_se_email_existe(): void
    {
        $this->post(route('password.email'), ['email' => 'naoexiste@exemplo.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }
}
