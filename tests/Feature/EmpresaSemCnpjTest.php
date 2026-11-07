<?php

namespace Tests\Feature;

use App\Http\Controllers\Empresas\EmpresaController;
use App\Http\Requests\Empresas\EmpresaRequest;
use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class EmpresaSemCnpjTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(Empresa $empresa): User
    {
        return User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);
    }

    public function test_cadastra_empresa_sem_cnpj(): void
    {
        $base = Empresa::create(['razao_social' => 'Base', 'cnpj' => '11222333000181']);
        $usuario = $this->usuario($base);
        $usuario->empresa_id = null;
        $this->actingAs($usuario);

        $request = EmpresaRequest::create(route('empresa.store'), 'POST', [
            'razao_social' => 'Oficina Sem Documento',
            'sem_cnpj' => '1',
            'cnpj' => '00.000.000/0000-00',
        ]);
        $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
        $request->setUserResolver(fn () => $usuario);
        $request->validateResolved();

        app(EmpresaController::class)->store($request);

        $empresa = Empresa::where('razao_social', 'Oficina Sem Documento')->first();

        $this->assertNotNull($empresa);
        $this->assertNull($empresa->cnpj);
        $this->assertSame($empresa->id, $usuario->fresh()->empresa_id);

        Empresa::create(['razao_social' => 'Outra Sem Documento', 'cnpj' => null]);
        $this->assertSame(2, Empresa::whereNull('cnpj')->count());

        $this->actingAs($usuario->fresh())
            ->get(route('empresa.index'))
            ->assertOk()
            ->assertSee('Oficina Sem Documento')
            ->assertSee('Sem CNPJ');
    }

    public function test_cadastro_sem_a_flag_exige_cnpj(): void
    {
        $empresa = Empresa::create(['razao_social' => 'Base', 'cnpj' => '11222333000181']);
        $usuario = $this->usuario($empresa);

        $this->actingAs($usuario)
            ->from(route('empresa.index'))
            ->post(route('empresa.store'), [
                'razao_social' => 'Oficina',
                'sem_cnpj' => '0',
                'cnpj' => '',
            ])
            ->assertSessionHasErrors([
                'cnpj' => 'Informe o CNPJ ou marque Cadastrar sem CNPJ.',
            ]);

        $this->assertDatabaseMissing('empresa', ['razao_social' => 'Oficina']);
    }

    public function test_formulario_de_cadastro_mostra_a_flag(): void
    {
        $empresa = Empresa::create(['razao_social' => 'Base', 'cnpj' => '11222333000181']);
        $this->actingAs($this->usuario($empresa));
        view()->share('errors', new ViewErrorBag);

        $html = view('empresa.create')->render();

        $this->assertStringContainsString('Cadastrar sem CNPJ', $html);
        $this->assertStringContainsString('name="sem_cnpj"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="sem_cnpj"[^>]*checked/', $html);
    }

    public function test_edicao_remove_o_cnpj_quando_a_flag_esta_marcada(): void
    {
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '11222333000181',
        ]);
        $usuario = User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);

        $this->actingAs($usuario)
            ->put(route('empresa.update', $empresa), [
                'razao_social' => 'Empresa Teste LTDA',
                'sem_cnpj' => '1',
                'cnpj' => '11.222.333/0001-81',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresa.index'));

        $this->assertNull($empresa->fresh()->cnpj);
    }

    public function test_edicao_de_empresa_sem_cnpj_marca_a_flag(): void
    {
        $empresa = Empresa::create([
            'razao_social' => 'Sem Documento',
            'cnpj' => null,
        ]);
        $usuario = User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);

        $resposta = $this->actingAs($usuario)->get(route('empresa.edit', $empresa));

        $resposta->assertOk()->assertSee('Cadastrar sem CNPJ');
        $this->assertMatchesRegularExpression('/id="sem_cnpj"[^>]*checked/', $resposta->getContent());
    }

    public function test_empresa_sem_cnpj_pode_receber_o_documento_depois(): void
    {
        $empresa = Empresa::create([
            'razao_social' => 'Sem Documento',
            'cnpj' => null,
        ]);
        $usuario = User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);

        $this->actingAs($usuario)
            ->put(route('empresa.update', $empresa), [
                'razao_social' => 'Sem Documento',
                'sem_cnpj' => '0',
                'cnpj' => '11.222.333/0001-81',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('11.222.333/0001-81', $empresa->fresh()->cnpj);
    }
}
