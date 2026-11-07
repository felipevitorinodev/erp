<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaCscNfceTest extends TestCase
{
    use RefreshDatabase;

    private const CSC = 'A1B2C3D4E5F60718293A4B5C6D7E8F901234';

    private function empresa(): Empresa
    {
        return Empresa::create([
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '11222333000181',
            'uf' => 'PB',
            'nfe_ambiente' => 2,
            'nfe_serie' => '001',
            'nfce_serie' => '001',
            'nfe_destino' => 1,
            'crt' => 1,
        ]);
    }

    private function admin(Empresa $empresa): User
    {
        return User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function dadosBase(): array
    {
        return [
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '11.222.333/0001-81',
        ];
    }

    public function test_csc_da_nfce_fica_criptografado_e_fora_da_tela(): void
    {
        $empresa = $this->empresa();
        $usuario = $this->admin($empresa);

        $this->actingAs($usuario)
            ->put(route('empresa.update', $empresa), $this->dadosBase() + [
                'nfce_csc_homologacao' => self::CSC,
                'nfce_id_csc_homologacao' => '1',
                'nfce_serie' => '2',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $empresa->refresh();

        $this->assertNotSame(self::CSC, $empresa->nfce_csc_homologacao);
        $this->assertSame(self::CSC, $empresa->nfce_csc_homologacao_decrypted);
        $this->assertSame('000001', $empresa->nfce_id_csc_homologacao);
        $this->assertSame('002', $empresa->nfce_serie);
        $this->assertNull($empresa->nfce_csc_producao);
        $this->assertArrayNotHasKey('nfce_csc_homologacao', $empresa->toArray());
        $this->assertArrayNotHasKey('certificado_senha', $empresa->toArray());

        $this->actingAs($usuario)
            ->get(route('empresa.edit', $empresa))
            ->assertOk()
            ->assertSee('Preenchido')
            ->assertSee('Vazio')
            ->assertSee('000001')
            ->assertDontSee(self::CSC);
    }

    public function test_csc_em_branco_mantem_o_valor_gravado(): void
    {
        $empresa = $this->empresa();
        $usuario = $this->admin($empresa);

        $this->actingAs($usuario)
            ->put(route('empresa.update', $empresa), $this->dadosBase() + [
                'nfce_csc_homologacao' => self::CSC,
                'nfce_id_csc_homologacao' => '000001',
            ])
            ->assertSessionHasNoErrors();

        $cifrado = $empresa->fresh()->nfce_csc_homologacao;

        $this->actingAs($usuario)
            ->put(route('empresa.update', $empresa), $this->dadosBase() + [
                'nfce_csc_homologacao' => '',
                'nfce_id_csc_homologacao' => '',
            ])
            ->assertSessionHasNoErrors();

        $empresa->refresh();
        $this->assertSame($cifrado, $empresa->nfce_csc_homologacao);
        $this->assertSame(self::CSC, $empresa->nfce_csc_homologacao_decrypted);
        $this->assertSame('000001', $empresa->nfce_id_csc_homologacao);
    }

    public function test_csc_sem_id_nao_grava_e_nao_volta_na_sessao(): void
    {
        $empresa = $this->empresa();

        $this->actingAs($this->admin($empresa))
            ->from(route('empresa.edit', $empresa))
            ->put(route('empresa.update', $empresa), $this->dadosBase() + [
                'nfce_csc_homologacao' => self::CSC,
            ])
            ->assertSessionHasErrors('nfce_id_csc_homologacao');

        $this->assertNull($empresa->fresh()->nfce_csc_homologacao);

        $old = session()->get('_old_input');
        $this->assertIsArray($old);
        $this->assertSame('Empresa Teste LTDA', $old['razao_social'] ?? null);
        $this->assertArrayNotHasKey('nfce_csc_homologacao', $old);
    }

    public function test_atribuicao_em_massa_nao_grava_o_csc(): void
    {
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '11222333000181',
            'nfce_csc_homologacao' => self::CSC,
            'nfce_csc_producao' => self::CSC,
        ]);

        $this->assertNull($empresa->fresh()->nfce_csc_homologacao);
        $this->assertNull($empresa->fresh()->nfce_csc_producao);
    }
}
