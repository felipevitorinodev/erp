<?php

namespace Tests\Feature;

use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\Nfes\Nfe;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use App\Services\Nfes\NfeBuilderService;
use App\Services\Nfes\NfePendenciasService;
use App\Services\Nfes\NfeSenderService;
use App\Services\Nfes\NfeVinculoFiscal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use NFePHP\NFe\Common\Standardize;
use Tests\TestCase;

class NfeEmissaoTest extends TestCase
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

    private function empresa(array $dados = []): Empresa
    {
        return Empresa::create(array_merge([
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '11222333000181',
            'ie' => '111111111111',
            'cep' => '01001000',
            'logradouro' => 'Praça da Sé',
            'numero' => '1',
            'bairro' => 'Sé',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
            'cod_ibge' => '3550308',
            'nfe_ambiente' => 2,
            'nfe_serie' => '001',
            'nfe_sequencia' => 6,
            'nfe_destino' => 1,
            'crt' => 1,
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

    /** NF-e (modelo 55) exige destinatário com CPF/CNPJ e endereço; sem isso a SEFAZ rejeita (237/726). */
    private function clienteIdentificado(Empresa $empresa): Cliente
    {
        return Cliente::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Cliente Teste',
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

    private function vendaConfirmada(Empresa $empresa): Venda
    {
        return Venda::create([
            'empresa_id' => $empresa->id,
            'cliente_id' => $this->clienteIdentificado($empresa)->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 10,
            'total' => 10,
            'situacao' => 'confirmada',
        ]);
    }

    public function test_reemissao_de_nfe_rejeitada_reaproveita_o_numero(): void
    {
        $empresa = $this->empresa();
        $venda = $this->vendaConfirmada($empresa);
        Nfe::create([
            'empresa_id' => $empresa->id,
            'venda_id' => $venda->id,
            'numero' => '000000005',
            'serie' => '001',
            'situacao' => 'rejeitada',
            'ambiente' => 2,
            'codigo_status' => '225',
        ]);

        $this->actingAs($this->admin($empresa))->post(route('nfe.emitir', $venda));

        $this->assertSame(1, Nfe::count());
        $this->assertSame('000000005', Nfe::first()->numero);
        $this->assertSame(6, $empresa->fresh()->nfe_sequencia);
        $this->assertSame('000000005', $venda->fresh()->nfe_numero);
    }

    public function test_numero_rejeitado_por_duplicidade_539_nao_e_reaproveitado(): void
    {
        $empresa = $this->empresa();
        $venda = $this->vendaConfirmada($empresa);
        Nfe::create([
            'empresa_id' => $empresa->id,
            'venda_id' => $venda->id,
            'numero' => '000000005',
            'serie' => '001',
            'situacao' => 'rejeitada',
            'ambiente' => 2,
            'codigo_status' => '539',
        ]);

        $this->actingAs($this->admin($empresa))->post(route('nfe.emitir', $venda));

        $this->assertSame(2, Nfe::count());
        $this->assertSame('000000006', Nfe::latest('id')->first()->numero);
        $this->assertSame(7, $empresa->fresh()->nfe_sequencia);
    }

    public function test_data_futura_nao_reserva_numero(): void
    {
        $empresa = $this->empresa();
        $venda = $this->vendaConfirmada($empresa);
        $venda->update(['data_venda' => now()->addDays(2)->toDateString()]);

        $this->actingAs($this->admin($empresa))
            ->post(route('nfe.emitir', $venda))
            ->assertSessionHas('error');

        $this->assertSame(0, Nfe::count());
        $this->assertSame(6, $empresa->fresh()->nfe_sequencia);
    }

    public function test_saida_anterior_a_emissao_nao_reserva_numero(): void
    {
        $empresa = $this->empresa();
        $venda = $this->vendaConfirmada($empresa);
        $venda->update(['data_saida' => now()->subDay()->toDateString()]);

        $this->actingAs($this->admin($empresa))
            ->post(route('nfe.emitir', $venda))
            ->assertSessionHas('error');

        $this->assertSame(0, Nfe::count());
        $this->assertSame(6, $empresa->fresh()->nfe_sequencia);
    }

    public function test_nfe_pendente_bloqueia_nova_emissao(): void
    {
        $empresa = $this->empresa();
        $venda = $this->vendaConfirmada($empresa);
        Nfe::create([
            'empresa_id' => $empresa->id,
            'venda_id' => $venda->id,
            'numero' => '000000005',
            'serie' => '001',
            'situacao' => 'pendente',
            'ambiente' => 2,
        ]);

        $this->actingAs($this->admin($empresa))
            ->post(route('nfe.emitir', $venda))
            ->assertSessionHas('error');

        $this->assertSame(1, Nfe::count());
        $this->assertSame(6, $empresa->fresh()->nfe_sequencia);
    }

    public function test_consultar_pendente_nunca_transmitida_vira_rejeitada(): void
    {
        $empresa = $this->empresa();
        $venda = $this->vendaConfirmada($empresa);
        $nfe = Nfe::create([
            'empresa_id' => $empresa->id,
            'venda_id' => $venda->id,
            'numero' => '000000005',
            'serie' => '001',
            'situacao' => 'pendente',
            'ambiente' => 2,
        ]);

        $this->actingAs($this->admin($empresa))->post(route('nfe.consultar', $nfe));

        $this->assertSame('rejeitada', $nfe->fresh()->situacao);
    }

    public function test_xml_e_consulta_de_outra_empresa_retornam_404(): void
    {
        $empresaA = $this->empresa();
        $empresaB = $this->empresa(['cnpj' => '11444777000161']);
        $nfe = Nfe::create([
            'empresa_id' => $empresaA->id,
            'numero' => '000000001',
            'serie' => '001',
            'situacao' => 'autorizada',
            'ambiente' => 2,
            'xml_autorizado' => '<nfeProc/>',
        ]);

        $usuarioB = $this->admin($empresaB);

        $this->actingAs($usuarioB)->get(route('nfe.xml', $nfe))->assertNotFound();
        $this->actingAs($usuarioB)->post(route('nfe.consultar', $nfe))->assertNotFound();
    }

    public function test_usuario_nao_edita_empresa_de_outro_tenant(): void
    {
        $empresaA = $this->empresa();
        $empresaB = $this->empresa(['cnpj' => '11444777000161']);

        $this->actingAs($this->admin($empresaB))
            ->get(route('empresa.edit', $empresaA))
            ->assertNotFound();

        $this->actingAs($this->admin($empresaB))
            ->put(route('empresa.update', $empresaA), [
                'razao_social' => 'Invasor',
                'cnpj' => '11.222.333/0001-81',
            ])
            ->assertNotFound();

        $this->assertSame('Empresa Teste LTDA', $empresaA->fresh()->razao_social);
    }

    public function test_troca_de_ambiente_exige_confirmacao(): void
    {
        $empresa = $this->empresa();
        $usuario = $this->admin($empresa);
        $dados = [
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '11.222.333/0001-81',
            'nfe_ambiente' => 1,
        ];

        $this->actingAs($usuario)
            ->put(route('empresa.update', $empresa), $dados)
            ->assertSessionHasErrors('confirmar_ambiente');
        $this->assertSame(2, $empresa->fresh()->nfe_ambiente);

        $this->actingAs($usuario)
            ->put(route('empresa.update', $empresa), $dados + ['confirmar_ambiente' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $empresa->fresh()->nfe_ambiente);
    }

    public function test_cep_da_empresa_preenche_o_codigo_ibge(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'ibge' => '3550308',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
            ], 200),
        ]);

        $empresa = $this->empresa(['cod_ibge' => null, 'cep' => null]);

        $this->actingAs($this->admin($empresa))
            ->put(route('empresa.update', $empresa), [
                'razao_social' => 'Empresa Teste LTDA',
                'cnpj' => '11.222.333/0001-81',
                'cep' => '01001-000',
                'logradouro' => 'Praça da Sé',
                'numero' => '1',
                'bairro' => 'Sé',
                'cidade' => 'São Paulo',
                'uf' => 'SP',
            ])
            ->assertSessionHasNoErrors();

        $empresa->refresh();
        $this->assertSame('3550308', $empresa->cod_ibge);
        $this->assertSame('01001-000', $empresa->cep);
        $this->assertSame('Sé', $empresa->bairro);
    }

    public function test_codigo_ibge_informado_e_mantido(): void
    {
        Http::fake();

        $empresa = $this->empresa();

        $this->actingAs($this->admin($empresa))
            ->put(route('empresa.update', $empresa), [
                'razao_social' => 'Empresa Teste LTDA',
                'cnpj' => '11.222.333/0001-81',
                'cep' => '01001000',
                'cidade' => 'São Paulo',
                'uf' => 'SP',
                'cod_ibge' => '3550308',
            ])
            ->assertSessionHasNoErrors();

        Http::assertNothingSent();
        $this->assertSame('3550308', $empresa->fresh()->cod_ibge);
    }

    public function test_endereco_sem_codigo_ibge_resolvido_pede_o_codigo(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response(['erro' => true], 200),
        ]);

        $empresa = $this->empresa(['cod_ibge' => null]);

        $this->actingAs($this->admin($empresa))
            ->put(route('empresa.update', $empresa), [
                'razao_social' => 'Empresa Teste LTDA',
                'cnpj' => '11.222.333/0001-81',
                'cep' => '01001000',
                'cidade' => 'São Paulo',
                'uf' => 'SP',
            ])
            ->assertSessionHasErrors('cod_ibge');

        $this->assertNull($empresa->fresh()->cod_ibge);
    }

    public function test_totais_da_nfe_somam_valor_bruto_e_descontos_dos_itens(): void
    {
        $empresa = $this->empresaComCertificado();

        $venda = new Venda([
            'empresa_id' => $empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 19.00,
            'desconto' => 2.00,
            'acrescimo' => 0,
            'total' => 17.00,
            'forma_pagamento' => 'Dinheiro',
            'situacao' => 'confirmada',
            'nfe_numero' => '000000001',
        ]);
        $item = new VendaItem([
            'produto_nome' => 'Produto',
            'produto_codigo' => 'P1',
            'ncm' => '61091000',
            'cfop' => '5102',
            'unidade' => 'UN',
            'csosn' => '102',
            'cst_pis' => '07',
            'cst_cofins' => '07',
            'quantidade' => 2,
            'preco_unitario' => 10,
            'desconto' => 1,
            'total' => 19,
        ]);
        $item->setRelation('produto', null);
        $venda->setRelation('itens', collect([$item]));
        $venda->setRelation('cliente', $this->clienteIdentificado($empresa));

        $xml = (new NfeBuilderService($empresa))->buildAndSign($venda);
        $dom = simplexml_load_string($xml);
        $dom->registerXPathNamespace('n', 'http://www.portalfiscal.inf.br/nfe');
        $tot = $dom->xpath('//n:ICMSTot')[0];

        $this->assertSame('20.00', (string) $tot->vProd);
        $this->assertSame('3.00', (string) $tot->vDesc);
        $this->assertSame('17.00', (string) $tot->vNF);
        $this->assertSame('17.00', (string) $dom->xpath('//n:detPag/n:vPag')[0]);
        $this->assertSame('9', (string) $dom->xpath('//n:transp/n:modFrete')[0]);
    }

    /** Achados da homologação SEFAZ (PB/SVRS) de 13/11/2026: 696, 237 e 726. */
    public function test_cnpj_sem_ie_sai_como_consumidor_final_e_com_ie_como_contribuinte(): void
    {
        $empresa = $this->empresaComCertificado();

        $semIe = $this->xmlDaVenda($empresa, $this->clienteEmMemoria(['cnpj' => '11444777000161']));
        $this->assertSame('9', (string) $semIe->xpath('//n:dest/n:indIEDest')[0]);
        $this->assertSame('1', (string) $semIe->xpath('//n:ide/n:indFinal')[0]);

        $comIe = $this->xmlDaVenda($empresa, $this->clienteEmMemoria(['cnpj' => '11444777000161', 'inscricao_estadual' => '110042490114']));
        $this->assertSame('1', (string) $comIe->xpath('//n:dest/n:indIEDest')[0]);
        $this->assertSame('0', (string) $comIe->xpath('//n:ide/n:indFinal')[0]);
    }

    public function test_nao_contribuinte_marcado_como_nao_consumidor_final_e_recusado(): void
    {
        $empresa = $this->empresaComCertificado();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('696');

        $this->xmlDaVenda($empresa, $this->clienteEmMemoria(['cpf' => '52998224725']), 0);
    }

    public function test_numero_vazio_no_endereco_do_cliente_sai_como_sn(): void
    {
        $empresa = $this->empresaComCertificado();

        $xml = $this->xmlDaVenda($empresa, $this->clienteEmMemoria(['cpf' => '52998224725', 'numero' => '']));

        $this->assertSame('S/N', (string) $xml->xpath('//n:enderDest/n:nro')[0]);
    }

    public function test_venda_sem_cliente_nao_reserva_numero_de_nfe(): void
    {
        $empresa = $this->empresa();
        $venda = $this->vendaConfirmada($empresa);
        $venda->update(['cliente_id' => null]);

        $this->actingAs($this->admin($empresa))
            ->post(route('nfe.emitir', $venda))
            ->assertSessionHas('error', fn ($erro) => str_contains($erro, '237'));

        $this->assertSame(0, Nfe::count());
        $this->assertSame(6, $empresa->fresh()->nfe_sequencia);
    }

    public function test_cliente_sem_endereco_nao_reserva_numero_de_nfe(): void
    {
        $empresa = $this->empresa();
        $venda = $this->vendaConfirmada($empresa);
        $venda->cliente->update(['logradouro' => null, 'bairro' => null]);

        $this->actingAs($this->admin($empresa))
            ->post(route('nfe.emitir', $venda))
            ->assertSessionHas('error', fn ($erro) => str_contains($erro, '726'));

        $this->assertSame(0, Nfe::count());
    }

    public function test_retorno_com_varios_protocolos_usa_o_da_chave_da_nota(): void
    {
        $chave1 = '35261111222333000181550010000000011000000011';
        $chave2 = '35261111222333000181550010000000021000000022';
        $prot = fn (string $chave, string $nProt) => '<protNFe versao="4.00"><infProt><tpAmb>2</tpAmb><verAplic>T</verAplic>'
            .'<chNFe>'.$chave.'</chNFe><dhRecbto>2026-11-13T10:00:00-03:00</dhRecbto><nProt>'.$nProt.'</nProt>'
            .'<digVal>AAAA</digVal><cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt></protNFe>';
        $retorno = '<retConsReciNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>2</tpAmb><verAplic>T</verAplic>'
            .'<nRec>351000000000001</nRec><cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>35</cUF>'
            .'<dhRecbto>2026-11-13T10:00:00-03:00</dhRecbto>'.$prot($chave1, '111').$prot($chave2, '222').'</retConsReciNFe>';
        $std = (new Standardize($retorno))->toStd();

        $sender = (new \ReflectionClass(NfeSenderService::class))->newInstanceWithoutConstructor();
        $extrair = new \ReflectionMethod($sender, 'extrairInfProt');

        $this->assertSame('222', (string) $extrair->invoke($sender, $std, $chave2)->nProt);
        $this->assertSame('111', (string) $extrair->invoke($sender, $std, $chave1)->nProt);
        $this->assertNull($extrair->invoke($sender, $std, str_repeat('9', 44)));
    }

    public function test_ie_isento_sai_como_nao_contribuinte_sem_a_tag_ie(): void
    {
        $empresa = $this->empresaComCertificado();
        $isento = $this->clienteEmMemoria(['cnpj' => '11444777000161', 'inscricao_estadual' => 'isento']);

        // indIEDest 2 (contribuinte isento) volta com 805 na SEFAZ-PB (homologação, 14/11/2026): sai como 9, sem a tag IE.
        $xml = $this->xmlDaVenda($empresa, $isento);
        $this->assertSame('9', (string) $xml->xpath('//n:dest/n:indIEDest')[0]);
        $this->assertSame([], $xml->xpath('//n:dest/n:IE'));
        $this->assertSame('1', (string) $xml->xpath('//n:ide/n:indFinal')[0]);
    }

    public function test_cfop_e_csosn_de_st_precisam_combinar(): void
    {
        $this->assertNull(NfeBuilderService::pendenciaCfopCsosn('5405', '500'));
        $this->assertNull(NfeBuilderService::pendenciaCfopCsosn('5102', '102'));
        $this->assertStringContainsString('386', (string) NfeBuilderService::pendenciaCfopCsosn('5102', '500'));
        $this->assertStringContainsString('386', (string) NfeBuilderService::pendenciaCfopCsosn('5405', '102'));

        $empresa = $this->empresaComCertificado();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('386');
        $this->xmlDaVenda($empresa, $this->clienteEmMemoria(['cpf' => '52998224725']), null, ['csosn' => '500']);
    }

    public function test_crt_2_nao_emite_com_csosn(): void
    {
        $empresa = $this->empresaComCertificado();
        $empresa->update(['crt' => 2]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('591');

        $this->xmlDaVenda($empresa->fresh(), $this->clienteEmMemoria(['cpf' => '52998224725']));
    }

    public function test_cst_99_vai_zerado_e_csosn_500_sem_st_retido_passam_no_schema(): void
    {
        $empresa = $this->empresaComCertificado();

        $xml = $this->xmlDaVenda($empresa, $this->clienteEmMemoria(['cpf' => '52998224725']), null, [
            'csosn' => '500',
            'cfop' => '5405',
            'cst_pis' => '99',
            'cst_cofins' => '49',
        ]);

        $this->assertSame('500', (string) $xml->xpath('//n:ICMSSN500/n:CSOSN')[0]);
        $this->assertSame([], $xml->xpath('//n:ICMSSN500/n:vICMSSTRet'));
        $this->assertSame('99', (string) $xml->xpath('//n:PISOutr/n:CST')[0]);
        $this->assertSame('0.00', (string) $xml->xpath('//n:PISOutr/n:vBC')[0]);
        $this->assertSame('0.0000', (string) $xml->xpath('//n:PISOutr/n:pPIS')[0]);
        $this->assertSame('0.00', (string) $xml->xpath('//n:PISOutr/n:vPIS')[0]);
        $this->assertSame('49', (string) $xml->xpath('//n:COFINSOutr/n:CST')[0]);
        $this->assertSame('0.00', (string) $xml->xpath('//n:COFINSOutr/n:vCOFINS')[0]);
        $this->assertSame('0.00', (string) $xml->xpath('//n:ICMSTot/n:vPIS')[0]);
    }

    public function test_reserva_fiscal_segue_a_orientacao_da_sefaz_pb(): void
    {
        $empresa = new Empresa(['uf' => 'PB']);
        $vinculo = new NfeVinculoFiscal($empresa, collect());
        $item = new VendaItem;

        $this->assertSame('102', $vinculo->csosn($item, null)['valor']);
        $this->assertSame('99', $vinculo->cst($item, null, 'cst_pis', 'cst_pis', 'cst_pis', 'nfe_cst_pis_padrao')['valor']);
    }

    public function test_data_de_emissao_com_30_dias_ou_mais_e_recusada_antes_de_reservar_numero(): void
    {
        $empresa = $this->empresa();
        $pendencias = app(NfePendenciasService::class);

        $venda = new Venda(['data_venda' => now()->subDays(30)->toDateString()]);
        $this->assertTrue(collect($pendencias->pendenciasDeData($venda, $empresa))->contains(fn ($p) => str_contains($p, '228')));

        $venda = new Venda(['data_venda' => now()->subDays(29)->toDateString()]);
        $this->assertSame([], $pendencias->pendenciasDeData($venda, $empresa));
    }

    public function test_prazo_de_cancelamento_da_nfce_na_paraiba_e_de_30_minutos(): void
    {
        $nota = function (int $modelo, string $uf, Carbon $autorizacao): Nfe {
            $nfe = new Nfe(['modelo' => $modelo, 'situacao' => 'autorizada', 'data_autorizacao' => $autorizacao]);
            $nfe->setRelation('empresa', new Empresa(['uf' => $uf]));

            return $nfe;
        };

        $this->assertTrue($nota(Nfe::MODELO_NFCE, 'PB', now()->subMinutes(29))->podeCancelar());
        $this->assertFalse($nota(Nfe::MODELO_NFCE, 'PB', now()->subMinutes(31))->podeCancelar());
        $this->assertSame('30 minutos', $nota(Nfe::MODELO_NFCE, 'PB', now())->prazoCancelamentoTexto());
        $this->assertTrue($nota(Nfe::MODELO_NFE, 'PB', now()->subHours(23))->podeCancelar());
        $this->assertTrue($nota(Nfe::MODELO_NFCE, 'SP', now()->subHours(23))->podeCancelar());
        $this->assertFalse($nota(Nfe::MODELO_NFE, 'PB', now()->subHours(25))->podeCancelar());
    }

    private function clienteEmMemoria(array $dados): Cliente
    {
        return new Cliente(array_merge([
            'nome' => 'Cliente Teste',
            'logradouro' => 'Rua Teste',
            'numero' => '10',
            'bairro' => 'Centro',
            'cidade' => 'São Paulo',
            'estado' => 'SP',
            'cep' => '01001000',
        ], $dados));
    }

    private function xmlDaVenda(Empresa $empresa, Cliente $cliente, ?int $indFinal = null, array $fiscalDoItem = []): \SimpleXMLElement
    {
        $venda = new Venda([
            'empresa_id' => $empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 10,
            'total' => 10,
            'forma_pagamento' => 'Dinheiro',
            'situacao' => 'confirmada',
            'nfe_numero' => '000000001',
            'ind_final' => $indFinal,
        ]);
        $item = new VendaItem(array_merge([
            'produto_nome' => 'Produto',
            'produto_codigo' => 'P1',
            'ncm' => '61091000',
            'cfop' => '5102',
            'unidade' => 'UN',
            'csosn' => '102',
            'cst_pis' => '07',
            'cst_cofins' => '07',
            'quantidade' => 1,
            'preco_unitario' => 10,
            'desconto' => 0,
            'total' => 10,
        ], $fiscalDoItem));
        $item->setRelation('produto', null);
        $venda->setRelation('itens', collect([$item]));
        $venda->setRelation('cliente', $cliente);

        $xml = simplexml_load_string((new NfeBuilderService($empresa))->buildAndSign($venda));
        $xml->registerXPathNamespace('n', 'http://www.portalfiscal.inf.br/nfe');

        return $xml;
    }

    private function empresaComCertificado(): Empresa
    {
        if (! function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('Extensão openssl indisponível.');
        }

        $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $cnfWindows = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (! getenv('OPENSSL_CONF') && is_file($cnfWindows)) {
            $config['config'] = $cnfWindows;
        }
        $chave = openssl_pkey_new($config);
        if ($chave === false) {
            $this->markTestSkipped('Não foi possível gerar chave de teste (openssl.cnf ausente).');
        }

        $csr = openssl_csr_new(['commonName' => 'EMPRESA TESTE:11222333000181'], $chave, $config);
        $x509 = openssl_csr_sign($csr, null, $chave, 30, $config);
        openssl_pkcs12_export($x509, $pfx, $chave, 'teste123');

        $relativo = 'certificados/teste_'.uniqid().'.pfx';
        File::ensureDirectoryExists(storage_path('app/certificados'));
        File::put(storage_path('app/'.$relativo), $pfx);
        $this->certificadoTeste = storage_path('app/'.$relativo);

        return $this->empresa([
            'certificado_path' => $relativo,
            'certificado_senha' => Crypt::encryptString('teste123'),
        ]);
    }
}
