<?php

namespace Tests\Feature;

use App\Impressao\DanfsePdf;
use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\Fiscal\Municipio;
use App\Models\Fiscal\NfseClassificacao;
use App\Models\Fiscal\NfseNbs;
use App\Models\Fiscal\NfseServico;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Nfes\Nfe;
use App\Models\Nfses\Nfse;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use App\Services\Nfes\NfeBuilderService;
use App\Services\Nfses\NfseBuilderService;
use App\Services\Nfses\NfseRegras;
use App\Services\Nfses\NfseService;
use App\Services\Vendas\DivisaoFiscalVenda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Tests\Support\SefinSimulada;
use Tests\TestCase;

class NfseEmissaoTest extends TestCase
{
    use RefreshDatabase;

    private ?string $certificadoTeste = null;

    protected function tearDown(): void
    {
        if ($this->certificadoTeste && is_file($this->certificadoTeste)) {
            File::delete($this->certificadoTeste);
        }

        parent::tearDown();
    }

    public function test_tabelas_oficiais_da_nfse_sao_carregadas_pela_migration(): void
    {
        $this->assertSame(338, NfseServico::count());
        $this->assertGreaterThan(900, NfseNbs::count());
        $this->assertSame(70, NfseClassificacao::count());
        $this->assertSame(5570, Municipio::count());
        $this->assertSame('Análise e desenvolvimento de sistemas.', NfseServico::where('codigo', '010101')->value('descricao'));
        $this->assertTrue((bool) NfseClassificacao::where('codigo', '000001')->value('exige_tributacao'));
        $this->assertFalse((bool) NfseClassificacao::where('codigo', '410001')->value('exige_tributacao'));
    }

    public function test_dps_valida_no_xsd_dos_dois_leiautes(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->vendaDeServico($empresa, $this->clienteCnpj($empresa));

        foreach ([NfseBuilderService::LEIAUTE_NT009, NfseBuilderService::LEIAUTE_PRODUCAO_ATUAL] as $leiaute) {
            config(['nfse.leiaute.2' => $leiaute]);
            $dados = $this->dados($empresa, $venda);
            $dados['numero'] = 7;

            $this->assertSame([], NfseRegras::validar($dados), 'leiaute '.$leiaute);

            $dps = (new NfseBuilderService($empresa))->dps($dados);

            $this->assertSame('DPS355030821122233300018100001000000000000007', $dps['id']);
            $this->assertStringContainsString('<Signature', $dps['xml']);
            $this->assertStringContainsString('<vDescIncond>10.00</vDescIncond>', $dps['xml']);
            $this->assertStringContainsString('<cTribNac>140101</cTribNac>', $dps['xml']);
            $this->assertStringNotContainsString('<xNome>Empresa Teste', $dps['xml'], 'tpEmit 1 não leva o nome do prestador (E0121)');

            if ($leiaute === NfseBuilderService::LEIAUTE_NT009) {
                $this->assertMatchesRegularExpression('#<tpEmit>1</tpEmit><finNFSe>0</finNFSe><cLocEmi>#', $dps['xml']);
                $this->assertStringContainsString('<trib><CST>000</CST><cClassTrib>000001</cClassTrib><gIBSCBS/></trib>', $dps['xml']);
            } else {
                $this->assertStringContainsString('<IBSCBS><finNFSe>0</finNFSe><cIndOp>050101</cIndOp>', $dps['xml']);
                $this->assertStringContainsString('<gIBSCBS><CST>000</CST><cClassTrib>000001</cClassTrib></gIBSCBS>', $dps['xml']);
            }
        }
    }

    public function test_mei_sem_tomador_vai_com_indicador_de_tributos_e_sem_aliquota(): void
    {
        $empresa = $this->empresaComCertificado(['crt' => 4]);
        $venda = $this->vendaDeServico($empresa, null);
        $dados = $this->dados($empresa, $venda);
        $dados['numero'] = 1;

        $this->assertSame([], NfseRegras::validar($dados));
        $xml = (new NfseBuilderService($empresa))->dps($dados)['xml'];

        $this->assertStringContainsString('<opSimpNac>2</opSimpNac>', $xml);
        $this->assertStringContainsString('<indTotTrib>0</indTotTrib>', $xml);
        $this->assertStringNotContainsString('<toma>', $xml);
        $this->assertStringNotContainsString('<pAliq>', $xml);
        $this->assertStringNotContainsString('<regApTribSN>', $xml);
    }

    public function test_regras_da_aliquota_do_issqn(): void
    {
        // Não optante: só com convênio inativo (E0617/E0619).
        $this->assertFalse(NfseRegras::aliquotaObrigatoria(1, null, 0, 1, false, true));
        $this->assertTrue(NfseRegras::aliquotaObrigatoria(1, null, 0, 1, false, false));
        // MEI nunca (E0600).
        $this->assertFalse(NfseRegras::aliquotaObrigatoria(2, null, 0, 1, false, false));
        // ME/EPP com ISSQN pelo SN: só com retenção (E0621/E0625/E0628/E0631).
        $this->assertTrue(NfseRegras::aliquotaObrigatoria(3, 1, 0, 1, true, true));
        $this->assertFalse(NfseRegras::aliquotaObrigatoria(3, 1, 0, 1, false, false));
        // ME/EPP com ISSQN fora do SN: só com convênio inativo (E0635/E0640).
        $this->assertFalse(NfseRegras::aliquotaObrigatoria(3, 2, 0, 1, false, true));
        $this->assertTrue(NfseRegras::aliquotaObrigatoria(3, 2, 0, 1, false, false));
        // Regime especial e não incidência nunca (E0604/E0602).
        $this->assertFalse(NfseRegras::aliquotaObrigatoria(1, null, 6, 1, false, false));
        $this->assertFalse(NfseRegras::aliquotaObrigatoria(1, null, 0, 4, false, false));
    }

    public function test_regras_locais_barram_o_que_a_sefin_recusaria(): void
    {
        $empresa = $this->empresa(['crt' => 4]);
        $cliente = $this->clienteCnpj($empresa, ['cep' => null, 'logradouro' => null, 'bairro' => null]);
        $venda = $this->vendaDeServico($empresa, $cliente);

        $erros = NfseRegras::validar($this->dados($empresa, $venda, [
            'c_trib_mun' => '001',
            'iss_retido' => true,
            'c_trib_nac' => '070201',
        ]));

        $texto = implode("\n", $erros);
        $this->assertStringContainsString('E0313', $texto);
        $this->assertStringContainsString('MEI não tem ISSQN retido', $texto);
        $this->assertStringContainsString('E0235', $texto);
        $this->assertStringContainsString('E0370', $texto);

        $semTomador = $this->vendaDeServico($this->empresa(['cnpj' => '11444777000161']), null);
        $erros = NfseRegras::validar($this->dados($semTomador->empresa()->first(), $semTomador, ['iss_retido' => true]));
        $this->assertStringContainsString('E0204', implode("\n", $erros));
    }

    public function test_emissao_pela_venda_autoriza_e_grava_os_codigos_no_produto(): void
    {
        $sefin = (new SefinSimulada)->registrar();
        $empresa = $this->empresaComCertificado();
        $cliente = $this->clienteCnpj($empresa);
        $venda = $this->vendaDeServico($empresa, $cliente);
        $admin = $this->admin($empresa);

        $tela = $this->actingAs($admin)->get(route('nfse.create', $venda));
        $tela->assertOk()
            ->assertSee('Código de tributação nacional')
            ->assertSee('Conserto de aparelho');

        $resposta = $this->actingAs($admin)->post(route('nfse.store', $venda), $this->formulario());

        $nfse = Nfse::sole();
        $resposta->assertRedirect(route('nfse.show', $nfse));
        $this->assertSame([], $sefin->errosXsd);
        $this->assertSame('autorizada', $nfse->situacao);
        $this->assertSame('1', $nfse->numero_nfse);
        $this->assertSame(50, strlen((string) $nfse->chave_acesso));
        $this->assertSame(1, $nfse->numero);
        $this->assertSame('100.00', $nfse->valor_servico);
        $this->assertSame('10.00', $nfse->valor_desconto);
        $this->assertSame('90.00', $nfse->valor_liquido);
        $this->assertStringContainsString('<infNFSe', (string) $nfse->xml_nfse);

        $produto = Produto::where('tipo', 2)->sole();
        $this->assertSame('140101', $produto->nfse_c_trib_nac);
        $this->assertSame('120011000', $produto->nfse_nbs);
        $this->assertSame('000001', $produto->nfse_class_trib);
        $this->assertSame('050101', $produto->nfse_ind_op);

        $this->actingAs($admin)->get(route('nfse.show', $nfse))->assertOk()->assertSee('Autorizada');
        $this->actingAs($admin)->get(route('venda.show', $venda))->assertOk()->assertSee('NFS-e');

        $pdf = (new DanfsePdf)->render($nfse);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_segunda_venda_ja_vem_com_os_codigos_memorizados(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->vendaDeServico($empresa, $this->clienteCnpj($empresa));
        Produto::where('tipo', 2)->update(['nfse_c_trib_nac' => '140201', 'nfse_nbs' => '120011000']);

        $this->actingAs($this->admin($empresa))
            ->get(route('nfse.create', $venda))
            ->assertOk()
            ->assertSee('14.02.01 - Assistência técnica.')
            ->assertSee('value="140201"', false);
    }

    public function test_rejeicao_guarda_o_motivo_e_a_nova_tentativa_reaproveita_o_numero(): void
    {
        $sefin = (new SefinSimulada)->registrar();
        $sefin->modo = 'rejeitada';
        $empresa = $this->empresaComCertificado();
        $venda = $this->vendaDeServico($empresa, $this->clienteCnpj($empresa));
        $admin = $this->admin($empresa);

        $this->actingAs($admin)->post(route('nfse.store', $venda), $this->formulario());

        $nfse = Nfse::sole();
        $this->assertSame('rejeitada', $nfse->situacao);
        $this->assertSame('E0312', $nfse->codigo_status);
        $this->assertSame('E0312', $nfse->mensagens[0]['codigo']);

        $sefin->modo = 'autorizada';
        $this->actingAs($admin)->post(route('nfse.store', $venda), $this->formulario());

        $this->assertSame(1, Nfse::count());
        $this->assertSame('autorizada', $nfse->fresh()->situacao);
        $this->assertSame(1, $nfse->fresh()->numero);
    }

    public function test_sem_resposta_fica_pendente_e_a_consulta_traz_a_nota(): void
    {
        $sefin = (new SefinSimulada)->registrar();
        $sefin->modo = 'sem_resposta';
        $empresa = $this->empresaComCertificado();
        $venda = $this->vendaDeServico($empresa, $this->clienteCnpj($empresa));
        $admin = $this->admin($empresa);

        $this->actingAs($admin)->post(route('nfse.store', $venda), $this->formulario())
            ->assertSessionHas('warning');

        $nfse = Nfse::sole();
        $this->assertSame('pendente', $nfse->situacao);
        $this->assertNotNull($nfse->id_dps);

        // Nova emissão fica barrada enquanto a pendente não for consultada.
        $this->actingAs($admin)->post(route('nfse.store', $venda), $this->formulario())
            ->assertSessionHas('error');
        $this->assertSame(1, Nfse::count());

        $sefin->modo = 'autorizada';
        $sefin->gerar((string) $nfse->id_dps, (string) $nfse->xml_assinado);
        $this->actingAs($admin)->post(route('nfse.consultar', $nfse));

        $this->assertSame('autorizada', $nfse->fresh()->situacao);
    }

    public function test_consulta_sem_dps_na_sefin_libera_nova_emissao(): void
    {
        $sefin = (new SefinSimulada)->registrar();
        $sefin->modo = 'sem_resposta';
        $empresa = $this->empresaComCertificado();
        $venda = $this->vendaDeServico($empresa, $this->clienteCnpj($empresa));
        $admin = $this->admin($empresa);
        $this->actingAs($admin)->post(route('nfse.store', $venda), $this->formulario());

        $sefin->modo = 'autorizada';
        $nfse = Nfse::sole();
        $this->actingAs($admin)->post(route('nfse.consultar', $nfse));

        $this->assertSame('rejeitada', $nfse->fresh()->situacao);
    }

    public function test_dps_duplicada_busca_a_nfse_ja_gerada(): void
    {
        $sefin = (new SefinSimulada)->registrar();
        $sefin->modo = 'duplicada';
        $empresa = $this->empresaComCertificado();
        $venda = $this->vendaDeServico($empresa, $this->clienteCnpj($empresa));

        $this->actingAs($this->admin($empresa))->post(route('nfse.store', $venda), $this->formulario());

        $this->assertSame('autorizada', Nfse::sole()->situacao);
        $this->assertContains('GET /SefinNacional/dps/'.Nfse::sole()->id_dps, $sefin->chamadas);
    }

    public function test_cancelamento_registra_o_evento(): void
    {
        $sefin = (new SefinSimulada)->registrar();
        $empresa = $this->empresaComCertificado();
        $venda = $this->vendaDeServico($empresa, $this->clienteCnpj($empresa));
        $admin = $this->admin($empresa);
        $this->actingAs($admin)->post(route('nfse.store', $venda), $this->formulario());
        $nfse = Nfse::sole();

        $this->actingAs($admin)->post(route('nfse.cancelar', $nfse), [
            'codigo_motivo' => '1',
            'motivo' => 'Valor do serviço informado errado',
        ])->assertRedirect(route('nfse.show', $nfse));

        $nfse->refresh();
        $this->assertSame('cancelada', $nfse->situacao);
        $this->assertNotNull($nfse->xml_cancelamento);
        $this->assertStringContainsString('<e101101><xDesc>Cancelamento de NFS-e</xDesc><cMotivo>1</cMotivo>', $sefin->eventos[0]);
        $this->assertStringContainsString('Id="PRE'.$nfse->chave_acesso.'101101"', $sefin->eventos[0]);
    }

    public function test_servicos_com_codigos_diferentes_sao_barrados(): void
    {
        $sefin = (new SefinSimulada)->registrar();
        $empresa = $this->empresaComCertificado();
        $venda = $this->vendaDeServico($empresa, $this->clienteCnpj($empresa));
        $outro = $this->produto($empresa, ['nome' => 'Instalação', 'tipo' => 2, 'nfse_c_trib_nac' => '140601']);
        Produto::where('nome', 'Conserto de aparelho')->update(['nfse_c_trib_nac' => '140101']);
        $this->item($venda, $outro, 50);
        $admin = $this->admin($empresa);

        $this->actingAs($admin)->get(route('nfse.create', $venda))
            ->assertOk()
            ->assertSee('códigos de tributação diferentes');

        $this->actingAs($admin)->post(route('nfse.store', $venda), $this->formulario())
            ->assertSessionHas('error');

        $this->assertSame(0, Nfse::count());
        $this->assertSame([], $sefin->dps);
    }

    public function test_pendencias_locais_nao_reservam_numero(): void
    {
        $sefin = (new SefinSimulada)->registrar();
        $empresa = $this->empresaComCertificado();
        $venda = $this->vendaDeServico($empresa, $this->clienteCnpj($empresa, ['cidade' => 'Cidade Inexistente']));

        $this->actingAs($this->admin($empresa))->post(route('nfse.store', $venda), $this->formulario())
            ->assertSessionHasErrors('nfse');

        $this->assertSame(0, Nfse::count());
        $this->assertSame([], $sefin->dps);
    }

    public function test_venda_mista_divide_itens_e_desconto(): void
    {
        $empresa = $this->empresa();
        $venda = $this->vendaDeServico($empresa, null);
        $produto = $this->produto($empresa, ['nome' => 'Peça', 'tipo' => 1, 'ncm' => '85177900']);
        $this->item($venda, $produto, 300);
        $venda->update(['subtotal' => 400, 'desconto' => 40, 'acrescimo' => 8, 'total' => 368]);
        $venda->refresh();

        $produtos = DivisaoFiscalVenda::parte($venda, false);
        $servicos = DivisaoFiscalVenda::parte($venda, true);

        $this->assertSame(1, $produtos['itens']->count());
        $this->assertSame(1, $servicos['itens']->count());
        $this->assertSame(30.0, $produtos['desconto']);
        $this->assertSame(10.0, $servicos['desconto']);
        $this->assertSame(6.0, $produtos['acrescimo']);
        $this->assertSame(2.0, $servicos['acrescimo']);
        $this->assertSame(round((float) $venda->total, 2), round($produtos['liquido'] + $servicos['liquido'], 2));
    }

    public function test_nfe_de_venda_mista_leva_so_os_produtos(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->vendaDeServico($empresa, $this->clienteCpf($empresa));
        $produto = $this->produto($empresa, ['nome' => 'Peça', 'tipo' => 1, 'ncm' => '85177900']);
        $this->item($venda, $produto, 300, ['ncm' => '85177900', 'cfop' => '5102', 'csosn' => '102', 'cst_pis' => '07', 'cst_cofins' => '07']);
        $venda->update(['desconto' => 40, 'forma_pagamento' => 'Dinheiro', 'nfe_numero' => '000000001']);

        $xml = simplexml_load_string((new NfeBuilderService($empresa))->buildAndSign($venda->fresh()));
        $xml->registerXPathNamespace('n', 'http://www.portalfiscal.inf.br/nfe');

        $this->assertCount(1, $xml->xpath('//n:det'));
        $this->assertSame('300.00', (string) $xml->xpath('//n:ICMSTot/n:vProd')[0]);
        $this->assertSame('30.00', (string) $xml->xpath('//n:ICMSTot/n:vDesc')[0]);
        $this->assertSame('270.00', (string) $xml->xpath('//n:ICMSTot/n:vNF')[0]);
    }

    public function test_nfe_de_venda_so_com_servicos_e_barrada(): void
    {
        $empresa = $this->empresa();
        $venda = $this->vendaDeServico($empresa, $this->clienteCpf($empresa));

        $this->actingAs($this->admin($empresa))->post(route('nfe.emitir', $venda))
            ->assertSessionHas('error', 'Esta venda só tem serviços. Emita a NFS-e pela venda.');

        $this->assertSame(0, Nfe::count());
    }

    public function test_busca_de_codigos_prioriza_correlacao_e_uso_da_empresa(): void
    {
        $empresa = $this->empresa();
        $admin = $this->admin($empresa);
        $this->produto($empresa, ['nome' => 'Serviço usado', 'tipo' => 2, 'nfse_c_trib_nac' => '140201']);

        $servicos = $this->actingAs($admin)->getJson(route('api.nfse.servicos.busca', ['q' => '14']))->assertOk()->json();
        $this->assertSame('140201', $servicos[0]['codigo']);
        $this->assertTrue($servicos[0]['usado']);

        $porTexto = $this->actingAs($admin)->getJson(route('api.nfse.servicos.busca', ['q' => 'assist']))->json();
        $this->assertSame('140201', $porTexto[0]['codigo'], 'descrição que começa pelo termo vem primeiro');

        $porCodigo = $this->actingAs($admin)->getJson(route('api.nfse.servicos.busca', ['q' => '14.01']))->json();
        $this->assertSame('140101', $porCodigo[0]['codigo']);

        $nbs = $this->actingAs($admin)->getJson(route('api.nfse.nbs.busca', ['servico' => '140101']))->json();
        $this->assertNotEmpty($nbs);
        $this->assertTrue($nbs[0]['sugerido']);

        $municipios = $this->actingAs($admin)->getJson(route('api.municipios.busca', ['q' => 'São Paulo/SP']))->json();
        $this->assertSame('3550308', $municipios[0]['codigo']);
    }

    public function test_quem_nao_tem_permissao_nao_ve_a_nfse(): void
    {
        $empresa = $this->empresa();
        $vendedor = User::factory()->create(['empresa_id' => $empresa->id, 'perfil' => 'vendedor', 'ativo' => true]);

        $this->actingAs($vendedor)->get(route('nfse.index'))->assertForbidden();
    }

    public function test_conferir_nfse_grava_a_situacao_do_convenio(): void
    {
        $sefin = (new SefinSimulada)->registrar();
        $sefin->convenio = 'inativo';
        $empresa = $this->empresaComCertificado();

        $this->actingAs($this->admin($empresa))
            ->post(route('empresa.nfse.verificar', $empresa))
            ->assertSessionHas('nfse_verificacao');

        $this->assertSame('inativo', $empresa->fresh()->nfse_convenio_situacao);
        $this->assertNotNull($empresa->fresh()->nfse_convenio_consultado_em);
    }

    public function test_tela_da_lista_abre(): void
    {
        $empresa = $this->empresa();

        $this->actingAs($this->admin($empresa))->get(route('nfse.index'))->assertOk()->assertSee('NFS-e');
    }

    public function test_pedido_de_cancelamento_valida_no_xsd_dos_dois_leiautes(): void
    {
        $empresa = $this->empresaComCertificado();
        $chave = '3550308'.'1'.'2'.'11222333000181'.str_pad('1', 13, '0', STR_PAD_LEFT).'2611'.'123456789'.'0';

        foreach ([NfseBuilderService::LEIAUTE_NT009, NfseBuilderService::LEIAUTE_PRODUCAO_ATUAL] as $leiaute) {
            $xml = (new NfseBuilderService($empresa))->cancelamento($chave, 2, '1', 'Valor do serviço informado errado', $leiaute);

            $this->assertStringContainsString('Id="PRE'.$chave.'101101"', $xml);
            $this->assertStringContainsString('<cMotivo>1</cMotivo>', $xml);
        }
    }

    public function test_emissao_no_leiaute_de_producao_passa_no_xsd(): void
    {
        config(['nfse.leiaute.2' => NfseBuilderService::LEIAUTE_PRODUCAO_ATUAL]);
        $sefin = (new SefinSimulada)->registrar();
        $empresa = $this->empresaComCertificado();
        $venda = $this->vendaDeServico($empresa, $this->clienteCnpj($empresa));

        $this->actingAs($this->admin($empresa))->post(route('nfse.store', $venda), $this->formulario());

        $this->assertSame([], $sefin->errosXsd);
        $this->assertSame('autorizada', Nfse::sole()->situacao);
        $this->assertSame(NfseBuilderService::LEIAUTE_PRODUCAO_ATUAL, Nfse::sole()->leiaute);
    }

    public function test_empresa_grava_configuracao_da_nfse(): void
    {
        $empresa = $this->empresa();
        $admin = $this->admin($empresa);
        $base = ['razao_social' => 'Empresa Teste LTDA', 'cnpj' => '11.222.333/0001-81', 'cep' => '01001-000', 'cod_ibge' => '3550308'];

        $this->actingAs($admin)->put(route('empresa.update', $empresa), $base + [
            'nfse_ambiente' => '2',
            'nfse_serie' => '00003',
            'nfse_regime_especial' => '0',
            'nfse_regime_apuracao_sn' => '1',
            'nfse_aliquota_iss' => '2,5',
            'nfse_ptot_trib_sn' => '6,00',
            'nfse_c_trib_nac' => '14.01.01 - Lubrificação',
            'nfse_nbs' => '1.2001.10.00',
            'nfse_class_trib' => '000001',
            'nfse_ind_op' => '050101',
        ])->assertSessionHasNoErrors();

        $empresa->refresh();
        $this->assertSame('3', $empresa->nfse_serie);
        $this->assertSame('2.50', $empresa->nfse_aliquota_iss);
        $this->assertSame('140101', $empresa->nfse_c_trib_nac);
        $this->assertSame('120011000', $empresa->nfse_nbs);

        $this->actingAs($admin)->get(route('empresa.edit', $empresa))
            ->assertOk()
            ->assertSee('Configurações NFS-e')
            ->assertSee('14.01.01 - Lubrificação', false);

        // Trocar o ambiente pede confirmação, como na NF-e.
        $this->actingAs($admin)->put(route('empresa.update', $empresa), $base + ['nfse_ambiente' => '1'])
            ->assertSessionHasErrors('confirmar_ambiente_nfse');
        $this->assertSame(2, $empresa->fresh()->nfse_ambiente);

        // Código fora da tabela oficial é recusado.
        $this->actingAs($admin)->put(route('empresa.update', $empresa), $base + ['nfse_c_trib_nac' => '999998'])
            ->assertSessionHasErrors('nfse_c_trib_nac');
    }

    public function test_produto_de_servico_grava_os_codigos_da_nfse(): void
    {
        $empresa = $this->empresa();
        $admin = $this->admin($empresa);
        $produto = $this->produto($empresa);

        $this->actingAs($admin)->put(route('produto.update', $produto), [
            'empresa_id' => $empresa->id,
            'nome' => 'Conserto de aparelho',
            'tipo' => '2',
            'preco_venda' => '100,00',
            'nfse_c_trib_nac' => '14.02.01',
            'nfse_nbs' => '120011000',
            'nfse_class_trib' => '',
            'nfse_ind_op' => '',
        ])->assertSessionHasNoErrors();

        $produto->refresh();
        $this->assertSame('140201', $produto->nfse_c_trib_nac);
        $this->assertSame('120011000', $produto->nfse_nbs);
        $this->assertNull($produto->nfse_class_trib);

        $this->actingAs($admin)->get(route('produto.edit', $produto))
            ->assertOk()
            ->assertSee('14.02.01 - Assistência técnica.', false);
    }

    public function test_retencao_marcada_fica_gravada_no_cliente(): void
    {
        (new SefinSimulada)->registrar();
        $empresa = $this->empresaComCertificado(['nfse_aliquota_iss' => 2.0]);
        $cliente = $this->clienteCnpj($empresa);
        $venda = $this->vendaDeServico($empresa, $cliente);

        $this->actingAs($this->admin($empresa))->post(route('nfse.store', $venda), $this->formulario([
            'iss_retido' => '1',
            'aliquota_iss' => '2,00',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue((bool) $cliente->fresh()->nfse_iss_retido);
        $this->assertStringContainsString('<tpRetISSQN>2</tpRetISSQN>', (string) Nfse::sole()->xml_assinado);
        $this->assertStringContainsString('<pAliq>2.00</pAliq>', (string) Nfse::sole()->xml_assinado);
    }

    // ---------------------------------------------------------------- apoio

    public function test_nova_nfse_grava_a_venda_e_emite_com_a_descricao_dos_servicos(): void
    {
        $sefin = (new SefinSimulada)->registrar();
        $empresa = $this->empresaComCertificado();
        $cliente = $this->clienteCnpj($empresa);
        $servico = $this->produto($empresa, ['nfse_c_trib_nac' => '140101', 'nfse_nbs' => '120011000', 'controla_estoque' => false]);
        FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Pix', 'tipo' => 'avista', 'gera_conta_receber' => false, 'ativo' => true]);
        $admin = $this->admin($empresa);

        $this->actingAs($admin)->get(route('nfse.index'))->assertOk()->assertSee(route('nfse.nova'));
        $this->actingAs($admin)->get(route('nfse.nova'))
            ->assertOk()
            ->assertSee('Nova NFS-e')
            ->assertSee('Código de tributação nacional')
            ->assertSee('Tomador e serviços');

        $resposta = $this->actingAs($admin)->post(route('nfse.salvar'), $this->formulario([
            'descricao' => '',
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'forma_pagamento' => 'Pix',
            'itens' => [['produto_id' => $servico->id, 'quantidade' => '2,00', 'preco_unitario' => '75,00', 'desconto' => '0,00']],
        ]));

        $resposta->assertSessionHasNoErrors()->assertSessionMissing('error');
        $nfse = Nfse::sole();
        $resposta->assertRedirect(route('nfse.show', $nfse));
        $this->assertSame([], $sefin->errosXsd);
        $this->assertSame('autorizada', $nfse->situacao);
        $this->assertSame('2 x Conserto de aparelho', $nfse->descricao);
        $this->assertSame('150.00', $nfse->valor_liquido);

        $venda = Venda::sole();
        $this->assertSame('confirmada', $venda->situacao);
        $this->assertSame($cliente->id, $venda->cliente_id);
        $this->assertSame($venda->id, $nfse->venda_id);
        $this->assertSame('000001', $servico->fresh()->nfse_class_trib);
    }

    public function test_nova_nfse_com_pendencia_ou_produto_nao_grava_nada(): void
    {
        $sefin = (new SefinSimulada)->registrar();
        $empresa = $this->empresaComCertificado();
        $servico = $this->produto($empresa, ['controla_estoque' => false]);
        $peca = $this->produto($empresa, ['nome' => 'Peça', 'tipo' => 1, 'ncm' => '84733049', 'controla_estoque' => false]);
        $admin = $this->admin($empresa);
        $venda = fn (array $itens, array $extra = []) => $this->formulario(array_merge([
            'data_venda' => now()->toDateString(),
            'itens' => $itens,
        ], $extra));

        // Retenção sem tomador: a Sefin recusaria (E0204). A venda é desfeita e o formulário volta preenchido.
        $this->actingAs($admin)
            ->from(route('nfse.nova'))
            ->post(route('nfse.salvar'), $venda([['produto_id' => $servico->id, 'quantidade' => '1', 'preco_unitario' => '100,00']], ['iss_retido' => '1', 'aliquota_iss' => '2,00']))
            ->assertRedirect(route('nfse.nova'))
            ->assertSessionHasErrors('nfse')
            ->assertSessionHasInput('itens');

        // Volta para a tela com as pendências e os serviços digitados. O cookie faz a sessão ser lida do armazenamento,
        // como no navegador (sem ele, a sessão em JSON do teste não remonta os erros).
        $this->actingAs($admin)
            ->withCookie(config('session.cookie'), session()->getId())
            ->get(route('nfse.nova'))
            ->assertOk()
            ->assertSee('A DPS seria recusada')
            ->assertSee('E0204')
            ->assertSee('Conserto de aparelho');

        // Produto não entra na NFS-e.
        $this->actingAs($admin)
            ->from(route('nfse.nova'))
            ->post(route('nfse.salvar'), $venda([['produto_id' => $peca->id, 'quantidade' => '1', 'preco_unitario' => '10,00']]))
            ->assertSessionHasErrors('itens.0.produto_id');

        $this->assertSame(0, Venda::query()->count());
        $this->assertSame(0, Nfse::query()->count());
        $this->assertSame([], $sefin->chamadas);
    }

    public function test_nova_nfse_exige_tambem_a_permissao_de_vendas(): void
    {
        $empresa = $this->empresa();
        Perfil::create(['empresa_id' => $empresa->id, 'slug' => 'fiscal', 'nome' => 'Fiscal', 'permissoes' => ['nfse'], 'ativo' => true]);
        $fiscal = User::factory()->create(['empresa_id' => $empresa->id, 'perfil' => 'fiscal', 'ativo' => true]);

        $this->actingAs($fiscal)->get(route('nfse.index'))->assertOk()->assertDontSee(route('nfse.nova'));
        $this->actingAs($fiscal)->get(route('nfse.nova'))->assertForbidden();
        $this->actingAs($fiscal)->post(route('nfse.salvar'), [])->assertForbidden();
    }

    public function test_menu_lateral_tem_as_listas_de_nfe_nfce_e_nfse(): void
    {
        $empresa = $this->empresa();

        $this->actingAs($this->admin($empresa))->get(route('nfse.index'))
            ->assertOk()
            ->assertSee(route('nfe.index'))
            ->assertSee(route('nfce.index'))
            ->assertSee(route('nfse.index'));
    }

    public function test_busca_de_produtos_separa_servicos_de_produtos(): void
    {
        $empresa = $this->empresa();
        $this->produto($empresa, ['nome' => 'Conserto', 'nfse_c_trib_nac' => '140101']);
        $this->produto($empresa, ['nome' => 'Cabo', 'tipo' => 1]);
        $admin = $this->admin($empresa);

        $servicos = $this->actingAs($admin)->getJson(route('api.produtos.busca', ['tipo' => 'servico']))->assertOk()->json();
        $this->assertSame(['Conserto'], array_column($servicos, 'nome'));
        $this->assertSame('140101', $servicos[0]['nfse']['c_trib_nac']);

        $produtos = $this->actingAs($admin)->getJson(route('api.produtos.busca', ['tipo' => 'produto']))->assertOk()->json();
        $this->assertSame(['Cabo'], array_column($produtos, 'nome'));

        $todos = $this->actingAs($admin)->getJson(route('api.produtos.busca'))->assertOk()->json();
        $this->assertCount(2, $todos);
    }

    public function test_comando_de_catalogos_desativa_codigo_que_saiu_da_tabela(): void
    {
        NfseNbs::query()->updateOrCreate(['codigo' => '999999999'], ['descricao' => 'Código antigo', 'ativo' => true]);

        $this->artisan('nfse:catalogos')->assertSuccessful();

        $this->assertFalse((bool) NfseNbs::query()->where('codigo', '999999999')->value('ativo'));
        $this->assertTrue((bool) NfseNbs::query()->where('codigo', '120011000')->value('ativo'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formulario(array $dados = []): array
    {
        return array_merge([
            'c_trib_nac' => '140101',
            'c_trib_mun' => '',
            'nbs' => '120011000',
            'class_trib' => '000001',
            'ind_op' => '050101',
            'municipio_prestacao' => '3550308',
            'descricao' => 'Conserto de aparelho',
            'iss_retido' => '0',
            'aliquota_iss' => '',
            'data_competencia' => now()->toDateString(),
            'memorizar' => '1',
        ], $dados);
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(Empresa $empresa, Venda $venda, array $entrada = []): array
    {
        $venda->load(['itens.produto', 'cliente']);
        $entrada = array_merge($this->formulario(), ['iss_retido' => false, 'aliquota_iss' => null], $entrada);

        return app(NfseService::class)->dadosDaEmissao($venda, $empresa, $entrada, DivisaoFiscalVenda::parte($venda, true));
    }

    private function empresa(array $dados = []): Empresa
    {
        return Empresa::create(array_merge([
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '11222333000181',
            'ie' => '111111111111',
            'im' => '12345',
            'email' => 'contato@empresa.test',
            'telefone' => '1133334444',
            'cep' => '01001000',
            'logradouro' => 'Praça da Sé',
            'numero' => '1',
            'bairro' => 'Sé',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
            'cod_ibge' => '3550308',
            'nfe_ambiente' => 2,
            'nfe_serie' => '001',
            'nfe_destino' => 1,
            'crt' => 1,
            'nfse_ambiente' => 2,
            'nfse_serie' => '1',
            'nfse_ptot_trib_sn' => 6.0,
        ], $dados));
    }

    private function empresaComCertificado(array $dados = []): Empresa
    {
        if (! function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('Extensão openssl indisponível.');
        }

        $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $chave = openssl_pkey_new($config);
        if ($chave === false) {
            $this->markTestSkipped('Não foi possível gerar chave de teste.');
        }

        $csr = openssl_csr_new(['commonName' => 'EMPRESA TESTE:11222333000181'], $chave, $config);
        $x509 = openssl_csr_sign($csr, null, $chave, 30, $config);
        openssl_pkcs12_export($x509, $pfx, $chave, 'teste123');

        $relativo = 'certificados/teste_nfse_'.uniqid().'.pfx';
        File::ensureDirectoryExists(storage_path('app/certificados'));
        File::put(storage_path('app/'.$relativo), $pfx);
        $this->certificadoTeste = storage_path('app/'.$relativo);

        return $this->empresa(array_merge([
            'certificado_path' => $relativo,
            'certificado_senha' => Crypt::encryptString('teste123'),
        ], $dados));
    }

    private function admin(Empresa $empresa): User
    {
        return User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);
    }

    private function clienteCnpj(Empresa $empresa, array $dados = []): Cliente
    {
        return Cliente::create(array_merge([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente Empresa LTDA',
            'cnpj' => '11444777000161',
            'inscricao_municipal' => '9876',
            'email' => 'financeiro@cliente.test',
            'telefone' => '(11) 99999-0000',
            'cep' => '01310100',
            'logradouro' => 'Avenida Paulista',
            'numero' => '1000',
            'bairro' => 'Bela Vista',
            'cidade' => 'Sao Paulo',
            'estado' => 'SP',
            'ativo' => true,
        ], $dados));
    }

    private function clienteCpf(Empresa $empresa): Cliente
    {
        return Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente Pessoa',
            'cpf' => '52998224725',
            'logradouro' => 'Rua Teste',
            'numero' => '10',
            'bairro' => 'Centro',
            'cidade' => $empresa->cidade,
            'estado' => $empresa->uf,
            'cep' => $empresa->cep,
            'ativo' => true,
        ]);
    }

    private function produto(Empresa $empresa, array $dados = []): Produto
    {
        return Produto::create(array_merge([
            'empresa_id' => $empresa->id,
            'nome' => 'Conserto de aparelho',
            'tipo' => 2,
            'preco_venda' => 100,
            'ativo' => true,
        ], $dados));
    }

    private function item(Venda $venda, Produto $produto, float $valor, array $dados = []): VendaItem
    {
        return VendaItem::create(array_merge([
            'venda_id' => $venda->id,
            'produto_id' => $produto->id,
            'produto_nome' => $produto->nome,
            'produto_codigo' => 'P'.$produto->id,
            'unidade' => 'UN',
            'quantidade' => 1,
            'preco_unitario' => $valor,
            'desconto' => 0,
            'total' => $valor,
        ], $dados));
    }

    /** Venda confirmada com um serviço de R$ 100,00 e R$ 10,00 de desconto na venda. */
    private function vendaDeServico(Empresa $empresa, ?Cliente $cliente): Venda
    {
        $venda = Venda::create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $cliente?->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 100,
            'desconto' => 10,
            'acrescimo' => 0,
            'total' => 90,
            'situacao' => 'confirmada',
        ]);

        $this->item($venda, $this->produto($empresa), 100);

        return $venda;
    }
}
