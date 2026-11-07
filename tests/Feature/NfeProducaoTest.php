<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Nfes\Nfe;
use App\Models\Nfes\NfeInutilizacao;
use App\Models\Nfes\NfeNumeracao;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use App\Services\Empresas\EmpresaService;
use App\Services\Nfes\NfePendenciasService;
use App\Services\Nfes\NfeSenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NfeProducaoTest extends TestCase
{
    use RefreshDatabase;

    private function empresa(array $dados = []): Empresa
    {
        return Empresa::create(array_merge([
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '11222333000181',
            'uf' => 'SP',
            'nfe_ambiente' => 2,
            'nfe_serie' => '001',
            'nfe_sequencia' => 6,
            'nfe_destino' => 1,
            'crt' => 1,
        ], $dados));
    }

    public function test_homologacao_nao_consome_a_sequencia_de_producao(): void
    {
        $empresa = $this->empresa();
        $service = app(EmpresaService::class);

        $this->assertSame('000000006', $service->proximoNumeronNfe($empresa));

        $empresa->nfe_ambiente = 1;
        $empresa->save();

        $this->assertSame('000000001', $service->proximoNumeronNfe($empresa->fresh()));
        $this->assertSame(6, NfeNumeracao::query()->where('ambiente', 2)->value('ultimo_numero'));
        $this->assertSame(1, NfeNumeracao::query()->where('ambiente', 1)->value('ultimo_numero'));
    }

    public function test_faixa_inutilizada_e_pulada_na_numeracao(): void
    {
        $empresa = $this->empresa(['nfe_sequencia' => 1]);
        NfeInutilizacao::query()->create([
            'empresa_id' => $empresa->id,
            'ambiente' => 2,
            'serie' => '001',
            'ano' => now()->format('y'),
            'numero_inicial' => 1,
            'numero_final' => 2,
            'justificativa' => 'Numeros pulados em homologacao',
            'situacao' => 'autorizada',
            'protocolo' => '135000000000000',
        ]);

        $numero = app(EmpresaService::class)->proximoNumeronNfe($empresa);

        $this->assertSame('000000003', $numero);
    }

    public function test_numeracao_da_nfe_ignora_notas_e_faixas_do_modelo_65(): void
    {
        $empresa = $this->empresa(['nfe_sequencia' => 1]);
        NfeNumeracao::query()->create([
            'empresa_id' => $empresa->id,
            'modelo' => Nfe::MODELO_NFCE,
            'ambiente' => 2,
            'serie' => '001',
            'ultimo_numero' => 50,
        ]);
        Nfe::query()->create([
            'empresa_id' => $empresa->id,
            'modelo' => Nfe::MODELO_NFCE,
            'numero' => '000000050',
            'serie' => '001',
            'situacao' => 'autorizada',
            'ambiente' => 2,
        ]);
        NfeInutilizacao::query()->create([
            'empresa_id' => $empresa->id,
            'modelo' => Nfe::MODELO_NFCE,
            'ambiente' => 2,
            'serie' => '001',
            'ano' => now()->format('y'),
            'numero_inicial' => 1,
            'numero_final' => 3,
            'justificativa' => 'Faixa apenas da NFC-e de teste',
            'situacao' => 'autorizada',
        ]);

        $numero = app(EmpresaService::class)->proximoNumeronNfe($empresa);

        $this->assertSame('000000001', $numero);
        $this->assertSame(1, NfeNumeracao::query()->where('modelo', Nfe::MODELO_NFE)->value('ultimo_numero'));
        $this->assertSame(50, NfeNumeracao::query()->where('modelo', Nfe::MODELO_NFCE)->value('ultimo_numero'));
    }

    public function test_sefaz_indisponivel_nao_consome_numero(): void
    {
        $empresa = $this->empresa();
        $venda = Venda::create([
            'empresa_id' => $empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 10,
            'total' => 10,
            'situacao' => 'confirmada',
        ]);
        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);

        app()->instance('nfe.sefaz.status', fn () => 'A SEFAZ está indisponível (código 108).');

        $this->actingAs($admin)->post(route('nfe.emitir', $venda));

        $this->assertSame(0, Nfe::query()->count());
        $this->assertSame(0, NfeNumeracao::query()->count());
        $this->assertSame(6, $empresa->fresh()->nfe_sequencia);
    }

    public function test_status_107_libera_e_108_bloqueia(): void
    {
        $this->assertNull(NfeSenderService::mensagemStatus('107', 'Servico em Operacao'));
        $mensagem = NfeSenderService::mensagemStatus('108', 'Servico Paralisado Momentaneamente');
        $this->assertStringContainsString('108', $mensagem);
        $this->assertStringContainsString('nenhum número foi usado', $mensagem);
    }

    public function test_aliquota_cadastrada_gera_aviso_sem_bloquear(): void
    {
        $empresa = $this->empresa();
        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Camiseta',
            'tipo' => 1,
            'preco_venda' => 10,
            'aliquota_icms' => 18,
        ]);
        $venda = new Venda(['empresa_id' => $empresa->id]);
        $item = new VendaItem([
            'produto_nome' => 'Camiseta',
            'ncm' => '61091000',
            'cfop' => '5102',
            'csosn' => '400',
            'cst_pis' => '07',
            'cst_cofins' => '07',
            'quantidade' => 1,
        ]);
        $item->setRelation('produto', $produto);
        $venda->setRelation('itens', collect([$item]));

        $avisos = app(NfePendenciasService::class)->avisosAliquota($venda);
        $pendencias = app(NfePendenciasService::class)->verificar($venda, $empresa);

        $this->assertCount(1, $avisos);
        $this->assertStringContainsString('ICMS', $avisos[0]);
        $this->assertNotContains($avisos[0], $pendencias);
    }

    public function test_rollback_restaura_o_maior_numero_entre_os_ambientes(): void
    {
        $empresa = $this->empresa(['nfe_sequencia' => 4]);
        NfeNumeracao::query()->create([
            'empresa_id' => $empresa->id,
            'ambiente' => 2,
            'serie' => '001',
            'ultimo_numero' => 8,
        ]);
        NfeNumeracao::query()->create([
            'empresa_id' => $empresa->id,
            'ambiente' => 1,
            'serie' => '001',
            'ultimo_numero' => 15,
        ]);
        Nfe::query()->create([
            'empresa_id' => $empresa->id,
            'numero' => '000000015',
            'serie' => '001',
            'situacao' => 'autorizada',
            'ambiente' => 1,
            'xml_autorizado' => '<nfeProc/>',
        ]);

        $migration = require base_path('database/migrations/2026_10_30_000004_create_nfe_numeracoes_table.php');
        $migration->down();

        $this->assertSame(16, $empresa->fresh()->nfe_sequencia);
        $this->assertSame('<nfeProc/>', Nfe::query()->first()->xml_autorizado);
        $this->assertFalse(Schema::hasTable('nfe_numeracoes'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('nfe_numeracoes'));
        $this->assertSame(15, NfeNumeracao::query()->where('ambiente', 1)->where('empresa_id', $empresa->id)->value('ultimo_numero'));
        $this->assertSame(15, NfeNumeracao::query()->where('ambiente', 2)->where('empresa_id', $empresa->id)->value('ultimo_numero'));
    }

    public function test_nota_rejeitada_pode_limpar_codigos_e_autorizada_nao(): void
    {
        $empresa = $this->empresa();
        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);
        $venda = Venda::create([
            'empresa_id' => $empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 10,
            'total' => 10,
            'situacao' => 'confirmada',
            'nfe_tpag' => '01',
        ]);
        $item = VendaItem::create([
            'venda_id' => $venda->id,
            'produto_nome' => 'Camiseta',
            'quantidade' => 1,
            'preco_unitario' => 10,
            'total' => 10,
            'cfop' => '5405',
            'csosn' => '102',
            'cst_pis' => '04',
            'cst_cofins' => '04',
        ]);
        $rejeitada = Nfe::create([
            'empresa_id' => $empresa->id,
            'venda_id' => $venda->id,
            'numero' => '000000009',
            'serie' => '001',
            'situacao' => 'rejeitada',
            'ambiente' => 2,
            'codigo_status' => '225',
            'xml_assinado' => '<NFe/>',
        ]);

        $this->actingAs($admin)
            ->post(route('nfe.limpar-codigos', $rejeitada))
            ->assertRedirect(route('nfe.show', $rejeitada));

        $item->refresh();
        $this->assertNull($item->cfop);
        $this->assertNull($item->csosn);
        $this->assertNull($venda->fresh()->nfe_tpag);
        $this->assertSame('rejeitada', $rejeitada->fresh()->situacao);
        $this->assertSame('<NFe/>', $rejeitada->fresh()->xml_assinado);

        $autorizada = Nfe::create([
            'empresa_id' => $empresa->id,
            'venda_id' => $venda->id,
            'numero' => '000000010',
            'serie' => '001',
            'situacao' => 'autorizada',
            'ambiente' => 2,
            'xml_autorizado' => '<nfeProc/>',
        ]);
        VendaItem::query()->whereKey($item->id)->update(['cfop' => '5102']);

        $this->actingAs($admin)
            ->post(route('nfe.limpar-codigos', $autorizada))
            ->assertSessionHas('error');

        $this->assertSame('5102', $item->fresh()->cfop);
        $this->assertSame('<nfeProc/>', $autorizada->fresh()->xml_autorizado);
    }

    public function test_inutilizacao_recusa_faixa_que_ja_tem_nota(): void
    {
        $empresa = $this->empresa();
        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);
        Nfe::create([
            'empresa_id' => $empresa->id,
            'numero' => '000000005',
            'serie' => '001',
            'situacao' => 'rejeitada',
            'ambiente' => 2,
            'codigo_status' => '539',
        ]);

        $this->actingAs($admin)->post(route('nfe.inutilizar.store'), [
            'serie' => '001',
            'ano' => now()->format('y'),
            'numero_inicial' => 5,
            'numero_final' => 5,
            'justificativa' => 'Numero ja usado na serie desta empresa',
        ])->assertSessionHas('error');

        $this->assertSame(0, NfeInutilizacao::query()->count());
    }
}
