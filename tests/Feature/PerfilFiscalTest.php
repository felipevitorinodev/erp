<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\PerfisFiscais\PerfilFiscal;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Services\Nfes\NfeVinculoFiscal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfilFiscalTest extends TestCase
{
    use RefreshDatabase;

    private function empresa(): Empresa
    {
        return Empresa::create([
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '11222333000181',
            'uf' => 'SP',
            'nfe_cfop_padrao' => '5102',
            'nfe_csosn_padrao' => '400',
            'nfe_cst_pis_padrao' => '07',
            'nfe_cst_cofins_padrao' => '07',
        ]);
    }

    private function produto(Empresa $empresa, array $dados = []): Produto
    {
        return Produto::create(array_merge([
            'empresa_id' => $empresa->id,
            'nome' => 'Camiseta',
            'tipo' => 1,
            'preco_venda' => 10,
            'ncm' => '61091000',
        ], $dados));
    }

    private function perfil(Empresa $empresa, array $dados = []): PerfilFiscal
    {
        return PerfilFiscal::create(array_merge([
            'empresa_id' => $empresa->id,
            'nome' => 'Revenda',
            'cfop' => '5405',
            'csosn' => '102',
            'cst_pis' => '04',
            'cst_cofins' => '04',
            'ativo' => true,
        ], $dados));
    }

    public function test_cfop_do_produto_vence_o_perfil_ligado(): void
    {
        $empresa = $this->empresa();
        $perfil = $this->perfil($empresa);
        $produto = $this->produto($empresa, [
            'cfop_padrao' => '5102',
            'perfil_fiscal_id' => $perfil->id,
        ]);
        $produto->load('perfilFiscal');

        $escolha = NfeVinculoFiscal::daEmpresa($empresa)->cfop((object) ['cfop' => null], $produto);

        $this->assertSame('5102', $escolha['valor']);
        $this->assertSame('produto', $escolha['origem']);
    }

    public function test_perfil_ligado_preenche_campo_vazio_do_produto(): void
    {
        $empresa = $this->empresa();
        $perfil = $this->perfil($empresa);
        $produto = $this->produto($empresa, ['perfil_fiscal_id' => $perfil->id]);
        $produto->load('perfilFiscal');
        $vinculo = NfeVinculoFiscal::daEmpresa($empresa);
        $item = (object) ['cfop' => '', 'csosn' => null, 'ncm' => '61091000'];

        $this->assertSame('perfil', $vinculo->cfop($item, $produto)['origem']);
        $this->assertSame('5405', $vinculo->cfop($item, $produto)['valor']);
        $this->assertSame('102', $vinculo->csosn($item, $produto)['valor']);
    }

    public function test_excecao_do_item_vence_produto_e_perfil(): void
    {
        $empresa = $this->empresa();
        $perfil = $this->perfil($empresa);
        $produto = $this->produto($empresa, [
            'cfop_padrao' => '5102',
            'perfil_fiscal_id' => $perfil->id,
        ]);

        $escolha = NfeVinculoFiscal::daEmpresa($empresa)->cfop((object) ['cfop' => '5910'], $produto);

        $this->assertSame('5910', $escolha['valor']);
        $this->assertSame('item', $escolha['origem']);
    }

    public function test_perfil_por_ncm_so_entra_quando_produto_esta_em_branco(): void
    {
        $empresa = $this->empresa();
        $this->perfil($empresa, ['nome' => 'Por NCM', 'ncm' => '61091000', 'cfop' => '5656']);
        $produto = $this->produto($empresa);
        $vinculo = NfeVinculoFiscal::daEmpresa($empresa);

        $escolha = $vinculo->cfop((object) ['cfop' => null, 'ncm' => '61091000'], $produto);

        $this->assertSame('5656', $escolha['valor']);
        $this->assertSame('regra', $escolha['origem']);
    }

    public function test_sem_perfil_usa_o_padrao_da_empresa(): void
    {
        $empresa = $this->empresa();
        $produto = $this->produto($empresa, ['ncm' => null]);

        $escolha = NfeVinculoFiscal::daEmpresa($empresa)->cfop((object) ['cfop' => null, 'ncm' => null], $produto);

        $this->assertSame('5102', $escolha['valor']);
        $this->assertSame('empresa', $escolha['origem']);
    }

    public function test_perfil_mais_especifico_vence_e_empate_fica_com_o_mais_antigo(): void
    {
        $empresa = $this->empresa();
        $geral = $this->perfil($empresa, ['nome' => 'Só NCM', 'ncm' => '61091000', 'cfop' => '5656']);
        $especifico = $this->perfil($empresa, [
            'nome' => 'NCM em SP',
            'ncm' => '61091000',
            'uf_destino' => 'SP',
            'cfop' => '5101',
        ]);
        $produto = $this->produto($empresa);
        $vinculo = NfeVinculoFiscal::daEmpresa($empresa)->usarContexto([
            'operacao' => 'venda',
            'uf_destino' => 'SP',
            'tipo_destinatario' => 'nao_contribuinte',
        ]);
        $item = (object) ['cfop' => null, 'ncm' => '61091000'];

        $escolha = $vinculo->cfop($item, $produto);

        $this->assertSame('5101', $escolha['valor']);
        $this->assertSame('regra', $escolha['origem']);
        $this->assertStringContainsString('NCM em SP', $escolha['motivo']);
        $this->assertStringContainsString('destino SP', $escolha['motivo']);
        $this->assertTrue($especifico->id > $geral->id);
    }

    public function test_perfil_ligado_vence_regra_mais_especifica(): void
    {
        $empresa = $this->empresa();
        $ligado = $this->perfil($empresa, ['nome' => 'Ligado', 'cfop' => '5405']);
        $this->perfil($empresa, [
            'nome' => 'Por UF',
            'ncm' => '61091000',
            'uf_destino' => 'SP',
            'operacao' => 'venda',
            'tipo_destinatario' => 'nao_contribuinte',
            'cfop' => '5101',
        ]);
        $produto = $this->produto($empresa, ['perfil_fiscal_id' => $ligado->id]);
        $produto->load('perfilFiscal');
        $vinculo = NfeVinculoFiscal::daEmpresa($empresa)->usarContexto([
            'operacao' => 'venda',
            'uf_destino' => 'SP',
            'tipo_destinatario' => 'nao_contribuinte',
        ]);

        $escolha = $vinculo->cfop((object) ['cfop' => null, 'ncm' => '61091000'], $produto);

        $this->assertSame('5405', $escolha['valor']);
        $this->assertSame('perfil', $escolha['origem']);
    }

    public function test_perfil_sem_contexto_nao_muda_o_padrao_da_empresa(): void
    {
        $empresa = $this->empresa();
        $this->perfil($empresa, [
            'nome' => 'Generico',
            'cfop' => '5405',
            'tipo_destinatario' => 'contribuinte',
        ]);
        $produto = $this->produto($empresa, ['ncm' => null]);
        $vinculo = NfeVinculoFiscal::daEmpresa($empresa)->usarContexto([
            'operacao' => 'venda',
            'uf_destino' => 'SP',
            'tipo_destinatario' => 'contribuinte',
        ]);

        $escolha = $vinculo->cfop((object) ['cfop' => null, 'ncm' => null], $produto);

        $this->assertSame('5102', $escolha['valor']);
        $this->assertSame('empresa', $escolha['origem']);
    }

    public function test_perfil_inativo_nao_e_usado(): void
    {
        $empresa = $this->empresa();
        $perfil = $this->perfil($empresa, ['ativo' => false, 'ncm' => '61091000']);
        $produto = $this->produto($empresa, ['perfil_fiscal_id' => $perfil->id]);
        $produto->load('perfilFiscal');

        $escolha = NfeVinculoFiscal::daEmpresa($empresa)->cfop((object) ['cfop' => null, 'ncm' => '61091000'], $produto);

        $this->assertSame('empresa', $escolha['origem']);
    }

    public function test_gtin_valido_e_invalido(): void
    {
        $this->assertTrue(NfeVinculoFiscal::gtinValido('7891000315507'));
        $this->assertFalse(NfeVinculoFiscal::gtinValido('7891000315508'));
        $this->assertFalse(NfeVinculoFiscal::gtinValido('123'));
    }

    public function test_admin_lista_perfis_e_vendedor_recebe_403(): void
    {
        $empresa = $this->empresa();
        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);
        $vendedor = User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'vendedor',
            'ativo' => true,
        ]);

        $this->actingAs($admin)->get(route('perfilFiscal.index'))->assertOk();
        $this->actingAs($vendedor)->get(route('perfilFiscal.index'))->assertForbidden();
    }

    public function test_cadastro_rejeita_cfop_interestadual_e_csosn_com_st(): void
    {
        $empresa = $this->empresa();
        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);

        $this->actingAs($admin)->post(route('perfilFiscal.store'), [
            'nome' => 'Fora do escopo',
            'cfop' => '6102',
            'csosn' => '500',
            'cst_pis' => '07',
            'cst_cofins' => '07',
            'ativo' => '1',
        ])->assertSessionHasErrors(['cfop', 'csosn']);

        $this->assertSame(0, PerfilFiscal::count());
    }
}
