<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Nfes\Nfe;
use App\Models\Nfes\NfeCartaCorrecao;
use App\Models\Usuarios\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use NFePHP\Common\Keys;
use Tests\Support\SefazSimulada;
use Tests\TestCase;

/**
 * Carta de Correção (evento 110110) com a SEFAZ simulada. Regras do MOC: só NF-e autorizada (580),
 * sequência de 1 a 20 (594), texto de 15 a 1000 caracteres, NFC-e não tem CC-e (784).
 */
class NfeCartaCorrecaoTest extends TestCase
{
    use RefreshDatabase;

    private ?string $certificadoTeste = null;

    private SefazSimulada $sefaz;

    private const CORRECAO = 'Onde se lê Rua Teste, 10, leia-se Rua Teste, 100.';

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

    public function test_carta_registrada_grava_o_evento_e_a_seguinte_usa_o_proximo_numero(): void
    {
        $empresa = $this->empresaComCertificado();
        $nota = $this->notaAutorizada($empresa);
        $this->actingAs($this->admin($empresa));

        // A biblioteca tira caracteres que a SEFAZ não aceita (aspas); grava-se o texto que foi registrado.
        $this->post(route('nfe.carta-correcao', $nota), ['correcao' => 'Onde se lê "Rua Teste, 10", leia-se "Rua Teste, 100".'])
            ->assertRedirect(route('nfe.show', $nota))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'nº 1'));

        $pedido = (string) $this->sefaz->ultimo('envEvento');
        $this->assertStringContainsString('<tpEvento>110110</tpEvento>', $pedido);
        $this->assertStringContainsString('<nSeqEvento>1</nSeqEvento>', $pedido);
        $this->assertStringContainsString('<xCondUso>A Carta de Correcao e disciplinada', $pedido);
        $this->assertStringContainsString('<tpAmb>2</tpAmb>', $pedido);

        $carta = NfeCartaCorrecao::query()->sole();
        $this->assertSame(1, $carta->sequencia);
        $this->assertSame(self::CORRECAO, $carta->correcao);
        $this->assertSame('135', $carta->codigo_status);
        $this->assertNotEmpty($carta->protocolo);
        $this->assertNotNull($carta->data_registro);
        $this->assertStringContainsString('<procEventoNFe', (string) $carta->xml);

        $this->post(route('nfe.carta-correcao', $nota), ['correcao' => self::CORRECAO.' Complemento: fundos.'])
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'nº 2'));
        $this->assertStringContainsString('<nSeqEvento>2</nSeqEvento>', (string) $this->sefaz->ultimo('envEvento'));

        $this->get(route('nfe.show', $nota))
            ->assertOk()
            ->assertSee('Cartas de correção')
            ->assertSee('Vigente')
            ->assertSee('Complemento: fundos.');

        $xml = $this->get(route('nfe.carta-correcao.arquivo', [$nota, $carta->id]))->assertOk();
        $this->assertStringContainsString('-cce-1-procEventoNFe.xml', (string) $xml->headers->get('Content-Disposition'));

        $pdf = $this->get(route('nfe.carta-correcao.arquivo', [$nota, $carta->id, 'tipo' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
    }

    public function test_carta_nao_vai_para_nota_cancelada_nem_com_texto_curto(): void
    {
        $empresa = $this->empresaComCertificado();
        $this->actingAs($this->admin($empresa));

        $cancelada = $this->notaAutorizada($empresa, ['situacao' => 'cancelada']);
        $this->post(route('nfe.carta-correcao', $cancelada), ['correcao' => self::CORRECAO])
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, '580'));

        $autorizada = $this->notaAutorizada($empresa, ['numero' => '000000002']);
        $this->post(route('nfe.carta-correcao', $autorizada), ['correcao' => 'curto'])
            ->assertSessionHasErrors('correcao');

        $this->assertSame(0, $this->sefaz->quantos('envEvento'));
        $this->assertSame(0, NfeCartaCorrecao::count());
    }

    public function test_vigesima_primeira_carta_nao_e_enviada(): void
    {
        $empresa = $this->empresaComCertificado();
        $nota = $this->notaAutorizada($empresa);
        foreach (range(1, Nfe::CCE_MAXIMO) as $sequencia) {
            NfeCartaCorrecao::create([
                'empresa_id' => $empresa->id,
                'nfe_id' => $nota->id,
                'sequencia' => $sequencia,
                'correcao' => self::CORRECAO,
            ]);
        }

        $this->actingAs($this->admin($empresa))
            ->post(route('nfe.carta-correcao', $nota), ['correcao' => self::CORRECAO])
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, '594'));

        $this->assertSame(0, $this->sefaz->quantos('envEvento'));
        $this->get(route('nfe.show', $nota))->assertOk()->assertDontSee('id="btn-abrir-cce"', false);
    }

    public function test_duplicidade_traz_a_carta_que_ja_estava_na_sefaz(): void
    {
        $empresa = $this->empresaComCertificado();
        $nota = $this->notaAutorizada($empresa);
        $this->actingAs($this->admin($empresa));

        // A SEFAZ registrou a carta nº 1, mas a resposta não ficou gravada aqui.
        $this->post(route('nfe.carta-correcao', $nota), ['correcao' => self::CORRECAO]);
        NfeCartaCorrecao::query()->delete();

        $this->sefaz->codigoEvento = '573';
        $this->post(route('nfe.carta-correcao', $nota), ['correcao' => self::CORRECAO.' Segunda tentativa.'])
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, '573') && str_contains($msg, 'trazido'));

        $recuperada = NfeCartaCorrecao::query()->sole();
        $this->assertSame(1, $recuperada->sequencia);
        $this->assertSame(self::CORRECAO, $recuperada->correcao);
        $this->assertStringContainsString('<procEventoNFe', (string) $recuperada->xml);

        $this->sefaz->codigoEvento = '135';
        $this->post(route('nfe.carta-correcao', $nota), ['correcao' => self::CORRECAO.' Segunda tentativa.'])
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'nº 2'));
    }

    public function test_nfce_nao_tem_carta_de_correcao(): void
    {
        $empresa = $this->empresaComCertificado();
        $nfce = $this->notaAutorizada($empresa, ['modelo' => Nfe::MODELO_NFCE]);

        $this->actingAs($this->admin($empresa))
            ->post(route('nfe.carta-correcao', $nfce), ['correcao' => self::CORRECAO])
            ->assertNotFound();

        $this->assertFalse($nfce->aceitaCartaCorrecao());
        $this->assertSame(0, $this->sefaz->quantos('envEvento'));
    }

    private function notaAutorizada(Empresa $empresa, array $dados = []): Nfe
    {
        $modelo = (int) ($dados['modelo'] ?? Nfe::MODELO_NFE);
        $numero = $dados['numero'] ?? '000000001';
        $chave = Keys::build('35', now()->format('y'), now()->format('m'), $empresa->cnpj, $modelo, 1, (int) $numero, 1);
        $protocolo = '135'.str_pad((string) random_int(1, 999999), 12, '0', STR_PAD_LEFT);
        $this->sefaz->notaAutorizada($chave, $protocolo);

        return Nfe::create(array_merge([
            'empresa_id' => $empresa->id,
            'modelo' => $modelo,
            'numero' => $numero,
            'serie' => '001',
            'chave_acesso' => $chave,
            'situacao' => 'autorizada',
            'ambiente' => 2,
            'protocolo' => $protocolo,
            'codigo_status' => '100',
            'xml_autorizado' => '<nfeProc/>',
            'data_autorizacao' => now(),
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

    private function empresaComCertificado(): Empresa
    {
        if (! function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('Extensão openssl indisponível.');
        }

        // Como um e-CNPJ ICP-Brasil: o CNPJ vai no OID 2.16.76.1.3.3 (o sped-nfe usa para montar o CNPJ do evento).
        $cnf = tempnam(sys_get_temp_dir(), 'cnf');
        file_put_contents($cnf, "[req]\ndistinguished_name=dn\n[dn]\n[ext]\nbasicConstraints=CA:FALSE\n"
            ."subjectAltName=otherName:2.16.76.1.3.3;OCTETSTRING:11222333000181\n");
        $config = ['config' => $cnf, 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256', 'x509_extensions' => 'ext'];
        $chave = openssl_pkey_new($config);
        if ($chave === false) {
            $this->markTestSkipped('Não foi possível gerar chave de teste.');
        }

        $csr = openssl_csr_new(['commonName' => 'EMPRESA TESTE:11222333000181'], $chave, $config);
        $x509 = openssl_csr_sign($csr, null, $chave, 30, $config);
        openssl_pkcs12_export($x509, $pfx, $chave, 'teste123');
        @unlink($cnf);

        $relativo = 'certificados/teste_'.uniqid().'.pfx';
        File::ensureDirectoryExists(storage_path('app/certificados'));
        File::put(storage_path('app/'.$relativo), $pfx);
        $this->certificadoTeste = storage_path('app/'.$relativo);

        return Empresa::create([
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
            'certificado_path' => $relativo,
            'certificado_senha' => Crypt::encryptString('teste123'),
        ]);
    }
}
