<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_product_of_other_company()
    {
        // criar duas empresas
        $empresaA = Empresa::create(['razao_social' => 'Empresa A', 'nome_fantasia' => 'A', 'cnpj' => '00000000000191']);
        $empresaB = Empresa::create(['razao_social' => 'Empresa B', 'nome_fantasia' => 'B', 'cnpj' => '00000000000272']);

        // criar produto para empresa A
        $produto = Produto::create([
            'empresa_id' => $empresaA->id,
            'nome' => 'Produto A',
            'tipo' => 1,
            'preco_venda' => 0,
        ]);

        // criar usuário da empresa B
        $user = User::factory()->create([
            'empresa_id' => $empresaB->id,
            'perfil'     => 'admin',
            'ativo'      => true,
        ]);

        // autenticar e tentar acessar rota de edição do produto (deve retornar 404)
        $response = $this->actingAs($user)->get(route('produto.edit', $produto));
        $response->assertStatus(404);
    }
}

