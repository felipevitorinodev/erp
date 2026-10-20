<?php

namespace Tests\Feature;

use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadastrosIsolamentoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Empresa $outra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['razao_social' => 'Empresa A', 'cnpj' => '11222333000181']);
        $this->outra = Empresa::create(['razao_social' => 'Empresa B', 'cnpj' => '11444777000161']);
        $this->actingAs(User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]));
    }

    private function postCliente(): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('cliente.store'), [
            'empresa_id' => $this->empresa->id,
            'nome' => 'Cliente',
            'tipo_pessoa' => 'PF',
            'cpf' => '529.982.247-25',
        ]);
    }

    public function test_cpf_cadastrado_em_outra_empresa_pode_ser_usado(): void
    {
        Cliente::create(['empresa_id' => $this->outra->id, 'nome' => 'Cliente B', 'cpf' => '529.982.247-25']);

        $this->postCliente()->assertSessionHasNoErrors();

        $this->assertSame(2, Cliente::where('cpf', '529.982.247-25')->count());
    }

    public function test_cpf_repetido_na_mesma_empresa_e_recusado(): void
    {
        Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Cliente A', 'cpf' => '529.982.247-25']);

        $this->postCliente()->assertSessionHasErrors('cpf');
    }

    public function test_busca_de_empresas_retorna_so_a_propria(): void
    {
        $ids = collect($this->getJson(route('api.empresas.busca'))->json())->pluck('id');

        $this->assertEquals([$this->empresa->id], $ids->all());
    }

    public function test_funcionario_nao_vincula_usuario_de_outra_empresa(): void
    {
        $usuarioAlheio = User::factory()->create(['empresa_id' => $this->outra->id, 'perfil' => 'admin', 'ativo' => true]);

        $this->post(route('funcionario.store'), [
            'empresa_id' => $this->empresa->id,
            'usuario_id' => $usuarioAlheio->id,
            'nome' => 'Funcionário',
        ])->assertSessionHasErrors('usuario_id');
    }

    public function test_empresa_em_uso_nao_e_excluida(): void
    {
        $this->delete(route('empresa.destroy', $this->empresa))->assertSessionHas('error');

        $this->assertNotSoftDeleted($this->empresa);
    }
}
