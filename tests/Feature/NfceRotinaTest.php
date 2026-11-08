<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Nfes\Nfe;
use App\Models\Nfes\NfeInutilizacao;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use App\Services\Empresas\EmpresaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Tests\Support\SefazSimulada;
use Tests\TestCase;

/**
 * Rotina completa da NFC-e com a SEFAZ simulada (Tests\Support\SefazSimulada):
 * o XML passa pela validação de schema do sped-nfe, é assinado, enviado, protocolado e impresso.
 */
class NfceRotinaTest extends TestCase
{
    use RefreshDatabase;

    private ?string $certificadoTeste = null;

    private SefazSimulada $sefaz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sefaz = new SefazSimulada;
        app()->instance('nfe.soap', $this->sefaz);
    }

    protected function tearDown(): void
    {
        if ($this->certificadoTeste && is_file($this->certificadoTeste)) {
            File::delete($this->certificadoTeste);
        }

        parent::tearDown();
    }

    public function test_emite_nfce_pela_venda_finalizada_imprime_e_baixa_o_xml(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->venda($empresa);
        $this->actingAs($this->usuario($empresa));

        $this->get(route('venda.show', $venda))
            ->assertOk()
            ->assertSee('Nota fiscal')
            ->assertSee('Emitir NFC-e')
            ->assertSee('Sem nota');

        $this->post(route('venda.nfce.emitir', $venda))
            ->assertRedirect(route('venda.show', $venda).'#nota-fiscal')
            ->assertSessionHas('success');

        $nota = Nfe::query()->sole();
        $this->assertSame(Nfe::MODELO_NFCE, (int) $nota->modelo);
        $this->assertSame('autorizada', $nota->situacao);
        $this->assertSame('000000001', $nota->numero);
        $this->assertNotEmpty($nota->protocolo);
        $this->assertStringContainsString('<nfeProc', (string) $nota->xml_autorizado);
        $this->assertStringContainsString('<qrCode>', (string) $nota->xml_autorizado);
        $this->assertSame(['consStatServ', 'enviNFe'], $this->sefaz->pedidos);
        $this->assertStringContainsString('nfce', strtolower($this->sefaz->urls[1]), 'Envio deve usar o endereço de NFC-e da SEFAZ.');

        $this->get(route('venda.show', $venda))
            ->assertOk()
            ->assertSee('Imprimir NFC-e')
            ->assertSee('Baixar XML')
            ->assertSee('Cancelar NFC-e')
            ->assertSee($nota->chave_acesso)
            ->assertSee('NFC-e (cupom fiscal)')
            ->assertDontSee('Emitir NFC-e');

        $danfe = $this->get(route('venda.nfce.danfe', $venda))->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $danfe->getContent());

        $xml = $this->get(route('nfce.xml', $nota))->assertOk();
        $this->assertStringContainsString($nota->chave_acesso.'-nfce.xml', (string) $xml->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<protNFe', (string) $xml->getContent());
    }

    public function test_cartao_vai_com_o_grupo_card_e_passa_no_schema(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->venda($empresa, ['forma_pagamento' => 'Cartão de Crédito']);
        $this->actingAs($this->usuario($empresa));

        $this->post(route('venda.nfce.emitir', $venda))->assertSessionHas('success');

        $enviado = (string) $this->sefaz->ultimo('enviNFe');
        $this->assertStringContainsString('<tPag>03</tPag>', $enviado);
        $this->assertStringContainsString('<card><tpIntegra>2</tpIntegra></card>', $enviado);
        $this->assertSame('autorizada', Nfe::query()->sole()->situacao);
    }

    public function test_cpf_digitado_na_venda_vai_para_a_nota(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->venda($empresa);
        $this->actingAs($this->usuario($empresa));

        $this->post(route('venda.nfce.emitir', $venda), ['documento' => '529.982.247-25'])->assertSessionHas('success');

        $this->assertStringContainsString('<CPF>52998224725</CPF>', (string) $this->sefaz->ultimo('enviNFe'));
    }

    public function test_rejeicao_mostra_o_motivo_e_reemite_com_o_mesmo_numero(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->venda($empresa);
        $this->actingAs($this->usuario($empresa));
        $this->sefaz->modo = 'rejeitada';

        $this->post(route('venda.nfce.emitir', $venda))->assertSessionHas('error');

        $nota = Nfe::query()->sole();
        $this->assertSame('rejeitada', $nota->situacao);
        $this->assertSame('391', $nota->codigo_status);
        $this->get(route('venda.show', $venda))
            ->assertSee('A SEFAZ rejeitou a NFC-e')
            ->assertSee('Emitir de novo')
            ->assertSee('O mesmo número será usado.');

        $this->sefaz->modo = 'autorizada';
        $this->post(route('venda.nfce.emitir', $venda))->assertSessionHas('success');

        $this->assertSame(1, Nfe::query()->count());
        $this->assertSame('autorizada', $nota->fresh()->situacao);
        $this->assertSame('000000001', $nota->fresh()->numero);
    }

    public function test_nfce_sem_resposta_fica_pendente_trava_a_serie_e_e_resolvida_pela_consulta(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->venda($empresa);
        $outra = $this->venda($empresa, ['numero' => '000002']);
        $this->actingAs($this->usuario($empresa));
        $this->sefaz->modo = 'sem_resposta';

        $this->post(route('venda.nfce.emitir', $venda))->assertSessionHas('error');

        $nota = Nfe::query()->sole();
        $this->assertSame('pendente', $nota->situacao);
        $this->get(route('venda.show', $venda))->assertSee('Consultar SEFAZ');

        // Outra venda não emite enquanto houver pendente: a mensagem diz qual venda consultar.
        $this->post(route('venda.nfce.emitir', $outra))
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'venda nº 000001') && str_contains($m, 'Consultar SEFAZ'));
        $this->assertSame(1, Nfe::query()->count());

        // Venda com NFC-e pendente não é cancelada.
        $this->post(route('venda.cancelar', $venda))
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'NFC-e desta venda está pendente'));

        $this->sefaz->modo = 'autorizada';
        $this->post(route('nfce.consultar', $nota))
            ->assertRedirect(route('venda.show', $venda).'#nota-fiscal')
            ->assertSessionHas('success', 'NFC-e nº 1 autorizada. Já pode imprimir.');

        $nota->refresh();
        $this->assertSame('autorizada', $nota->situacao);
        $this->assertStringContainsString('<nfeProc', (string) $nota->xml_autorizado);
        $this->get(route('venda.nfce.danfe', $venda))->assertOk();
    }

    public function test_consulta_de_nfce_que_nao_chegou_na_sefaz_libera_o_numero(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->venda($empresa);
        $this->actingAs($this->usuario($empresa));
        $this->sefaz->modo = 'sem_resposta';
        $this->post(route('venda.nfce.emitir', $venda));
        $nota = Nfe::query()->sole();

        // Uma SEFAZ que nunca recebeu a nota responde 217.
        app()->instance('nfe.soap', $sefazVazia = new SefazSimulada);
        $this->post(route('nfce.consultar', $nota))
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'mesmo número'));
        $this->assertSame('rejeitada', $nota->fresh()->situacao);

        $this->post(route('venda.nfce.emitir', $venda))->assertSessionHas('success');
        $this->assertSame(1, Nfe::query()->count());
        $this->assertSame('000000001', $nota->fresh()->numero);
        $this->assertSame(1, $sefazVazia->quantos('enviNFe'));
    }

    public function test_cancela_nfce_e_depois_a_venda(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->venda($empresa);
        $this->actingAs($this->usuario($empresa));
        $this->post(route('venda.nfce.emitir', $venda));
        $nota = Nfe::query()->sole();

        $this->post(route('venda.cancelar', $venda))
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'NFC-e autorizada') && str_contains($m, 'Cancele a NFC-e primeiro'));
        $this->assertSame('confirmada', $venda->fresh()->situacao);

        $this->post(route('nfce.cancelar', $nota), ['justificativa' => 'curta'])
            ->assertSessionHasErrors('justificativa');
        $this->assertSame(0, $this->sefaz->quantos('envEvento'));

        $this->post(route('nfce.cancelar', $nota), ['justificativa' => 'Venda registrada em duplicidade pelo caixa'])
            ->assertSessionHas('success', 'NFC-e nº 1 cancelada na SEFAZ.');

        $nota->refresh();
        $this->assertSame('cancelada', $nota->situacao);
        $this->assertNotNull($nota->data_cancelamento);
        $this->assertStringContainsString('<procEventoNFe', (string) $nota->xml_cancelamento);
        $this->assertStringContainsString('<tpEvento>110111</tpEvento>', (string) $this->sefaz->ultimo('envEvento'));

        $this->get(route('venda.show', $venda))
            ->assertSee('foi cancelada na SEFAZ')
            ->assertSee('XML do cancelamento')
            ->assertSee('Emitir nova NFC-e');
        $xml = $this->get(route('nfce.xml', ['nfe' => $nota, 'cancelamento' => 1]))->assertOk();
        $this->assertStringContainsString('-procEventoNFe.xml', (string) $xml->headers->get('Content-Disposition'));

        $this->post(route('venda.cancelar', $venda))->assertSessionHas('success');
        $this->assertSame('cancelada', $venda->fresh()->situacao);
    }

    public function test_cancelamento_recusado_pela_sefaz_explica_o_motivo(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->venda($empresa);
        $this->actingAs($this->usuario($empresa));
        $this->post(route('venda.nfce.emitir', $venda));
        $nota = Nfe::query()->sole();
        $this->sefaz->codigoEvento = '501';

        $this->post(route('nfce.cancelar', $nota), ['justificativa' => 'Venda registrada em duplicidade pelo caixa'])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'código 501') && str_contains($m, 'contador'));

        $this->assertSame('autorizada', $nota->fresh()->situacao);
    }

    public function test_cancelar_nfce_exige_a_permissao_de_cancelar_venda(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->venda($empresa);
        $this->actingAs($this->usuario($empresa));
        $this->post(route('venda.nfce.emitir', $venda));
        $nota = Nfe::query()->sole();

        $vendedor = $this->usuario($empresa, 'vendedor');
        $this->actingAs($vendedor);

        $this->get(route('venda.show', $venda))
            ->assertOk()
            ->assertSee('Imprimir NFC-e')
            ->assertDontSee('Cancelar NFC-e');
        $this->post(route('nfce.cancelar', $nota), ['justificativa' => 'Venda registrada em duplicidade pelo caixa'])
            ->assertForbidden();
        $this->assertSame('autorizada', $nota->fresh()->situacao);
    }

    public function test_nfce_de_outra_empresa_ou_nfe_nao_abrem_pelas_rotas_da_nfce(): void
    {
        $empresa = $this->empresaComCertificado();
        $venda = $this->venda($empresa);
        $this->actingAs($this->usuario($empresa));
        $this->post(route('venda.nfce.emitir', $venda));
        $nota = Nfe::query()->sole();

        $nfe = Nfe::create([
            'empresa_id' => $empresa->id,
            'venda_id' => $venda->id,
            'modelo' => Nfe::MODELO_NFE,
            'numero' => '000000009',
            'serie' => '001',
            'situacao' => 'pendente',
            'ambiente' => 2,
        ]);
        $this->post(route('nfce.consultar', $nfe))->assertNotFound();
        $this->get(route('nfce.xml', $nfe))->assertNotFound();

        $outraEmpresa = Empresa::create(['razao_social' => 'Outra', 'cnpj' => '04252011000110', 'uf' => 'PB']);
        $this->actingAs($this->usuario($outraEmpresa));
        $this->get(route('nfce.xml', $nota))->assertNotFound();
        $this->post(route('nfce.cancelar', $nota), ['justificativa' => 'Venda registrada em duplicidade pelo caixa'])->assertNotFound();
        $this->post(route('venda.nfce.emitir', $venda))->assertNotFound();
    }

    public function test_inutiliza_faixa_de_nfce_e_a_numeracao_pula_a_faixa(): void
    {
        $empresa = $this->empresaComCertificado();
        $this->actingAs($this->usuario($empresa));

        $this->get(route('nfe.inutilizar'))->assertOk()->assertSee('NFC-e (modelo 65)');

        $this->post(route('nfe.inutilizar.store'), [
            'modelo' => '65',
            'serie' => '1',
            'ano' => now()->format('y'),
            'numero_inicial' => 1,
            'numero_final' => 2,
            'justificativa' => 'Numeros pulados por falha no caixa',
        ])->assertRedirect(route('nfe.inutilizar', ['modelo' => 65]))->assertSessionHas('success');

        $pedido = (string) $this->sefaz->ultimo('inutNFe');
        $this->assertStringContainsString('<mod>65</mod>', $pedido);
        $this->assertStringContainsString('nfce', strtolower((string) end($this->sefaz->urls)));

        $inutilizacao = NfeInutilizacao::query()->sole();
        $this->assertSame(Nfe::MODELO_NFCE, (int) $inutilizacao->modelo);
        $this->assertSame('autorizada', $inutilizacao->situacao);
        $this->assertStringContainsStringIgnoringCase('<procInutNFe', (string) $inutilizacao->xml);

        $this->get(route('nfe.inutilizar'))->assertSee('NFC-e')->assertSee('1 a 2');
        $this->assertSame('000000003', app(EmpresaService::class)->proximoNumeronNfe($empresa->fresh(), Nfe::MODELO_NFCE));
        $this->assertSame('000000001', app(EmpresaService::class)->proximoNumeronNfe($empresa->fresh(), Nfe::MODELO_NFE));
    }

    public function test_conferir_nfce_passa_quando_tudo_esta_certo(): void
    {
        $empresa = $this->empresaComCertificado();
        $this->actingAs($this->usuario($empresa));

        $this->post(route('empresa.nfce.verificar', $empresa))
            ->assertRedirect(route('empresa.edit', $empresa).'#nfce');

        $itens = collect(session('nfce_verificacao'));
        $this->assertSame(['Ambiente', 'Certificado digital', 'Dados da empresa e CSC', 'Notas pendentes', 'SEFAZ'], $itens->pluck('titulo')->all());
        $this->assertTrue($itens->every(fn (array $i) => $i['ok']));
        $this->assertSame(['consStatServ'], $this->sefaz->pedidos);

        $this->get(route('empresa.edit', $empresa))
            ->assertSee('Tudo certo para emitir NFC-e.')
            ->assertSee('Homologação: as notas são de teste');
    }

    public function test_conferir_nfce_aponta_o_que_falta_sem_mostrar_documentos(): void
    {
        $empresa = $this->empresa(['cod_ibge' => null]);
        $this->actingAs($this->usuario($empresa));

        $this->post(route('empresa.nfce.verificar', $empresa));
        $itens = collect(session('nfce_verificacao'))->keyBy('titulo');

        $this->assertFalse($itens['Certificado digital']['ok']);
        $this->assertFalse($itens['Dados da empresa e CSC']['ok']);
        $this->assertFalse($itens['SEFAZ']['ok']);
        $this->assertSame([], $this->sefaz->pedidos);
        $this->assertStringNotContainsString('11222333000181', json_encode($itens->all()));

        $this->get(route('empresa.edit', $empresa))->assertSee('Corrija os 3 itens marcados antes de emitir.');
    }

    public function test_conferir_nfce_avisa_certificado_de_outra_empresa_e_perto_de_vencer(): void
    {
        $empresa = $this->empresaComCertificado([], '04252011000110');
        $this->actingAs($this->usuario($empresa));
        $this->post(route('empresa.nfce.verificar', $empresa));
        $certificado = collect(session('nfce_verificacao'))->firstWhere('titulo', 'Certificado digital');
        $this->assertFalse($certificado['ok']);
        $this->assertStringContainsString('outra empresa', $certificado['texto']);
        $this->assertStringNotContainsString('04252011000110', $certificado['texto']);

        File::delete((string) $this->certificadoTeste);
        $empresa = $this->empresaComCertificado(['cnpj' => '11222333000262'], '11222333000181', 20);
        $this->actingAs($this->usuario($empresa));
        $this->post(route('empresa.nfce.verificar', $empresa));
        $certificado = collect(session('nfce_verificacao'))->firstWhere('titulo', 'Certificado digital');
        $this->assertTrue($certificado['ok'], 'Filial (mesma raiz de CNPJ) usa o certificado da matriz.');
        $this->assertTrue($certificado['aviso']);
        $this->assertStringContainsString('providencie a renovação', $certificado['texto']);
    }

    public function test_sefaz_fora_no_pdv_explica_sem_termo_tecnico_e_diz_que_da_para_emitir_depois(): void
    {
        $empresa = $this->empresaComCertificado(['nfce_emitir_pdv' => true]);
        $produto = $this->produto($empresa);
        FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Dinheiro', 'tipo' => 'avista', 'gera_conta_receber' => false, 'ativo' => true, 'dias_vencimento' => 0]);
        $this->actingAs($this->usuario($empresa));
        $this->sefaz->modo = 'fora';

        $resposta = $this->postJson(route('venda.store'), [
            'data_venda' => now()->toDateString(),
            'forma_pagamento' => 'Dinheiro',
            'situacao' => 'confirmada',
            'itens' => [['produto_id' => $produto->id, 'quantidade' => '1', 'preco_unitario' => '10,00']],
        ])->assertCreated();

        $resposta->assertJsonPath('nfce.ok', false);
        $mensagem = (string) $resposta->json('nfce.mensagem');
        $this->assertStringContainsString('Tente emitir de novo em alguns minutos.', $mensagem);
        $this->assertStringNotContainsString('tpEmis', $mensagem);
        $this->assertStringContainsString('Ver venda', (string) $resposta->json('nfce.dica'));
        $this->assertSame(0, Nfe::query()->count());

        // Com a SEFAZ de volta, a nota sai pela tela da venda.
        $this->sefaz->modo = 'autorizada';
        $venda = Venda::query()->sole();
        $this->post(route('venda.nfce.emitir', $venda))->assertSessionHas('success');
        $this->assertSame('autorizada', Nfe::query()->sole()->situacao);
    }

    public function test_placeholder_dos_500_reais_so_aparece_na_paraiba(): void
    {
        $empresa = $this->empresa(['nfce_emitir_pdv' => true, 'uf' => 'PE', 'cod_ibge' => '2611606', 'cidade' => 'Recife']);
        $this->actingAs($this->usuario($empresa));
        $this->get(route('pdv.index'))->assertOk()->assertDontSee('Opcional abaixo de R$ 500,00');

        $empresa->update(['uf' => 'PB']);
        $this->get(route('pdv.index'))->assertOk()->assertSee('Opcional abaixo de R$ 500,00');
    }

    public function test_venda_sem_nfce_configurada_nao_oferece_emitir_nfce(): void
    {
        $empresa = $this->empresa();
        $venda = $this->venda($empresa);

        $this->actingAs($this->usuario($empresa, 'vendedor'));
        $this->get(route('venda.show', $venda))->assertOk()->assertDontSee('id="nota-fiscal"', false);

        $this->actingAs($this->usuario($empresa));
        $this->get(route('venda.show', $venda))
            ->assertOk()
            ->assertSee('Emitir NF-e')
            ->assertDontSee('Emitir NFC-e')
            ->assertSee('cadastre o certificado digital e o CSC');
    }

    private function venda(Empresa $empresa, array $dados = []): Venda
    {
        $produto = $this->produto($empresa);
        $venda = Venda::create(array_merge([
            'empresa_id' => $empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 10,
            'total' => 10,
            'forma_pagamento' => 'Dinheiro',
            'situacao' => 'confirmada',
            'natureza_operacao' => 'VENDA DE MERCADORIA',
            'mod_frete' => 9,
        ], $dados));
        VendaItem::create([
            'venda_id' => $venda->id,
            'produto_id' => $produto->id,
            'produto_nome' => 'Produto',
            'produto_codigo' => 'P1',
            'quantidade' => 1,
            'preco_unitario' => 10,
            'desconto' => 0,
            'total' => 10,
            'ncm' => '61091000',
            'unidade' => 'UN',
        ]);

        return $venda;
    }

    private function empresa(array $dados = []): Empresa
    {
        return Empresa::create(array_merge([
            'razao_social' => 'Empresa Teste LTDA',
            'nome_fantasia' => 'Empresa Teste',
            'cnpj' => '11222333000181',
            'ie' => '111111111111',
            'cep' => '58010000',
            'logradouro' => 'Rua das Flores',
            'numero' => '10',
            'bairro' => 'Centro',
            'cidade' => 'João Pessoa',
            'uf' => 'PB',
            'cod_ibge' => '2507507',
            'nfe_ambiente' => 2,
            'nfe_serie' => '001',
            'nfce_serie' => '001',
            'nfe_sequencia' => 1,
            'nfe_destino' => 1,
            'crt' => 1,
            'nfce_emitir_pdv' => false,
        ], $dados));
    }

    private function produto(Empresa $empresa): Produto
    {
        return Produto::query()->firstOrCreate(['empresa_id' => $empresa->id, 'codigo' => 'P1'], [
            'nome' => 'Produto',
            'tipo' => 1,
            'preco_venda' => 10,
            'ncm' => '61091000',
            'controla_estoque' => false,
            'ativo' => true,
        ]);
    }

    private function usuario(Empresa $empresa, string $perfil = 'admin'): User
    {
        return User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => $perfil,
            'ativo' => true,
        ]);
    }

    private function empresaComCertificado(array $dados = [], string $cnpjDoCertificado = '11222333000181', int $dias = 365): Empresa
    {
        if (! function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('Extensão openssl indisponível.');
        }

        // Como um e-CNPJ ICP-Brasil: o CNPJ vai no OID 2.16.76.1.3.3 (o sped-nfe usa para montar CNPJ/CPF dos eventos).
        $cnf = tempnam(sys_get_temp_dir(), 'cnf');
        file_put_contents($cnf, "[req]\ndistinguished_name=dn\n[dn]\n[ext]\nbasicConstraints=CA:FALSE\n"
            ."subjectAltName=otherName:2.16.76.1.3.3;OCTETSTRING:{$cnpjDoCertificado}\n");
        $config = ['config' => $cnf, 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256', 'x509_extensions' => 'ext'];
        $chave = openssl_pkey_new($config);
        if ($chave === false) {
            $this->markTestSkipped('Não foi possível gerar chave de teste.');
        }

        $csr = openssl_csr_new(['commonName' => 'EMPRESA TESTE:'.$cnpjDoCertificado], $chave, $config);
        $x509 = openssl_csr_sign($csr, null, $chave, $dias, $config);
        openssl_pkcs12_export($x509, $pfx, $chave, 'teste123');
        @unlink($cnf);

        $relativo = 'certificados/teste_nfce_rotina_'.uniqid().'.pfx';
        File::ensureDirectoryExists(storage_path('app/certificados'));
        File::put(storage_path('app/'.$relativo), $pfx);
        $this->certificadoTeste = storage_path('app/'.$relativo);

        $empresa = $this->empresa(array_merge([
            'certificado_path' => $relativo,
            'certificado_senha' => Crypt::encryptString('teste123'),
        ], $dados));
        $empresa->nfce_csc_homologacao = Crypt::encryptString('A1B2C3D4E5F60718293A4B5C6D7E8F901234');
        $empresa->nfce_id_csc_homologacao = '000001';
        $empresa->save();

        return $empresa;
    }
}
