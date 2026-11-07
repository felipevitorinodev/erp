<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use App\Support\SessaoLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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

    private function sessao(User $usuario, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $usuario->id,
            'payload' => 'teste',
            'last_activity' => time(),
        ]);
    }

    public function test_trocar_a_propria_senha_encerra_os_outros_acessos(): void
    {
        $this->sessao($this->admin, 'outro-dispositivo');

        $this->actingAs($this->admin)->put(route('usuario.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'password' => 'senha-nova',
            'password_confirmation' => 'senha-nova',
            'perfil' => 'admin',
            'ativo' => 1,
        ])->assertRedirect(route('usuario.index'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertDatabaseMissing('sessions', ['id' => 'outro-dispositivo']);
        $this->assertTrue(Hash::check('senha-nova', $this->admin->fresh()->password));
    }

    public function test_alterar_nome_nao_encerra_sessao(): void
    {
        $this->sessao($this->admin, 'sessao-mantida');

        $this->actingAs($this->admin)->put(route('usuario.update', $this->admin), [
            'name' => 'Administrador renomeado',
            'email' => $this->admin->email,
            'perfil' => 'admin',
            'ativo' => 1,
        ])->assertRedirect(route('usuario.index'));

        $this->assertDatabaseHas('sessions', ['id' => 'sessao-mantida']);
        $this->assertTrue(Hash::check('password', $this->admin->fresh()->password));
    }

    public function test_trocar_senha_de_outro_usuario_encerra_as_sessoes_dele(): void
    {
        $vendedor = $this->usuario('vendedor');
        $this->sessao($vendedor, 'sessao-vendedor');

        $this->actingAs($this->admin)->put(route('usuario.update', $vendedor), [
            'name' => $vendedor->name,
            'email' => $vendedor->email,
            'password' => 'senha-nova',
            'password_confirmation' => 'senha-nova',
            'perfil' => 'vendedor',
            'ativo' => 1,
        ])->assertRedirect(route('usuario.index'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-vendedor']);
        $this->assertTrue(Hash::check('senha-nova', $vendedor->fresh()->password));
        $this->assertFalse(Hash::check('password', $vendedor->fresh()->password));
    }

    public function test_login_bloqueia_apos_cinco_tentativas(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.post'), ['email' => $this->admin->email, 'password' => 'errada']);
        }

        $this->post(route('login.post'), ['email' => $this->admin->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_login_nao_revela_se_o_email_existe(): void
    {
        $mensagem = 'E-mail ou senha inválidos.';

        $this->post(route('login.post'), [
            'email' => $this->admin->email,
            'password' => 'errada',
        ])->assertSessionHasErrors(['login' => $mensagem]);

        $this->post(route('login.post'), [
            'email' => 'nao-existe@dev.com',
            'password' => 'errada',
        ])->assertSessionHasErrors(['login' => $mensagem]);
    }

    public function test_login_so_volta_para_endereco_interno(): void
    {
        $this->withSession(['url.intended' => 'https://exemplo-externo.test/roubo'])
            ->post(route('login.post'), [
                'email' => $this->admin->email,
                'password' => 'password',
            ])
            ->assertRedirect('/');

        $this->assertAuthenticated();
    }

    public function test_login_mantem_pagina_interna_pedida(): void
    {
        $this->withSession(['url.intended' => '/venda'])
            ->post(route('login.post'), [
                'email' => $this->admin->email,
                'password' => 'password',
            ])
            ->assertRedirect('/venda');
    }

    public function test_sessao_expirada_avisa_ao_abrir_o_login(): void
    {
        $this->withCookie(SessaoLogin::MARCADOR, '1')
            ->get(route('login'))
            ->assertOk()
            ->assertSee('Sua sessão expirou. Entre novamente para continuar.');
    }

    public function test_abrir_o_login_nao_fala_de_sessao_expirada(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Sua sessão expirou');
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

    public function test_redefinir_senha_nao_revela_se_o_email_existe(): void
    {
        $mensagem = 'Este token de redefinição de senha é inválido.';
        $payload = fn (string $email) => [
            'token' => 'token-invalido',
            'email' => $email,
            'password' => 'senha-nova',
            'password_confirmation' => 'senha-nova',
        ];

        $this->post(route('password.update'), $payload('naoexiste@exemplo.com'))
            ->assertSessionHasErrors(['email' => $mensagem]);

        $this->post(route('password.update'), $payload($this->admin->email))
            ->assertSessionHasErrors(['email' => $mensagem]);

        $inativo = $this->usuario('vendedor', ['ativo' => false]);

        $this->post(route('password.update'), $payload($inativo->email))
            ->assertSessionHasErrors(['email' => $mensagem]);

        $this->assertTrue(Hash::check('password', $this->admin->fresh()->password));
        $this->assertTrue(Hash::check('password', $inativo->fresh()->password));
    }

    public function test_redefinir_senha_de_usuario_inativo_com_token_valido_nao_grava(): void
    {
        $inativo = $this->usuario('vendedor', ['ativo' => false]);
        $token = Password::broker()->createToken($inativo);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $inativo->email,
            'password' => 'senha-nova',
            'password_confirmation' => 'senha-nova',
        ])->assertSessionHasErrors([
            'email' => 'Usuário inativo. Entre em contato com o administrador.',
        ]);

        $this->assertTrue(Hash::check('password', $inativo->fresh()->password));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $inativo->email]);
    }

    public function test_redefinir_senha_encerra_sessoes_anteriores(): void
    {
        DB::table('sessions')->insert([
            'id' => 'sessao-anterior',
            'user_id' => $this->admin->id,
            'payload' => 'teste',
            'last_activity' => time(),
        ]);

        $token = Password::broker()->createToken($this->admin);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->admin->email,
            'password' => 'senha-nova',
            'password_confirmation' => 'senha-nova',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Senha redefinida com sucesso. Faça login.');

        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-anterior']);
        $this->assertTrue(Hash::check('senha-nova', $this->admin->fresh()->password));
        $this->assertGuest();
    }

    public function test_redefinir_senha_desconecta_quem_ja_estava_logado(): void
    {
        $token = Password::broker()->createToken($this->admin);

        $this->actingAs($this->admin)
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $this->admin->email,
                'password' => 'senha-nova',
                'password_confirmation' => 'senha-nova',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Senha redefinida com sucesso. Faça login.');

        $this->assertGuest();
        $this->get(route('login'))->assertOk()->assertSee('Senha redefinida com sucesso. Faça login.');
    }

    public function test_usuario_inativo_em_pedido_json_recebe_401(): void
    {
        $inativo = $this->usuario('vendedor', ['ativo' => false]);

        $this->actingAs($inativo)
            ->getJson('/')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Usuário inativo. Entre em contato com o administrador.',
            ]);

        $this->assertGuest();
    }

    public function test_usuario_inativo_em_pagina_continua_indo_para_o_login(): void
    {
        $inativo = $this->usuario('vendedor', ['ativo' => false]);

        $this->actingAs($inativo)
            ->get('/')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'login' => 'Usuário inativo. Entre em contato com o administrador.',
            ]);

        $this->assertGuest();
    }

    public function test_telas_de_senha_repetem_o_retorno_do_login(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-ver-senha="password"', false)
            ->assertSee('data-espera="Entrando..."', false);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('data-espera="Enviando..."', false);

        $this->get(route('password.reset', ['token' => 'token-teste']))
            ->assertOk()
            ->assertSee('data-ver-senha="password"', false)
            ->assertSee('data-ver-senha="password_confirmation"', false)
            ->assertSee('data-espera="Salvando..."', false);
    }
}
