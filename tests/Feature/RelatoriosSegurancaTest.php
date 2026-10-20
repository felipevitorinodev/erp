<?php

namespace Tests\Feature;

use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatoriosSegurancaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['razao_social' => 'Empresa A', 'cnpj' => '11222333000181']);
    }

    private function usuario(string $perfil): User
    {
        return User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => $perfil,
            'ativo' => true,
        ]);
    }

    public function test_relatorio_de_contas_exige_permissao_financeiro(): void
    {
        Perfil::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'slug' => 'analista'],
            ['nome' => 'Analista', 'permissoes' => ['relatorios'], 'sistema' => false, 'ativo' => true],
        );

        $this->actingAs($this->usuario('analista'))
            ->get(route('relatorio.contas-receber.csv'))
            ->assertForbidden();
    }

    public function test_csv_neutraliza_formulas(): void
    {
        ContaReceber::create([
            'empresa_id' => $this->empresa->id,
            'descricao' => '=HYPERLINK("http://x")',
            'valor' => 10,
            'valor_pago' => 0,
            'data_vencimento' => now()->toDateString(),
            'situacao' => 'aberta',
        ]);

        $csv = $this->actingAs($this->usuario('admin'))
            ->get(route('relatorio.contas-receber.csv'))
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_data_invalida_nao_gera_erro_500(): void
    {
        $this->actingAs($this->usuario('admin'))
            ->get(route('relatorio.vendas', ['data_inicio' => 'abc']))
            ->assertRedirect()
            ->assertSessionHasErrors('data_inicio');
    }
}
