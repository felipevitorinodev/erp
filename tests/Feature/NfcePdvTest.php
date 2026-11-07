<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Nfes\Nfe;
use App\Models\Nfes\NfeNumeracao;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use App\Services\Empresas\EmpresaService;
use App\Services\Nfces\NfceBuilderService;
use App\Services\Nfces\NfceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NfcePdvTest extends TestCase
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

    public function test_pdv_sem_a_flag_nao_emite_nfce(): void
    {
        $empresa = $this->empresa();
        $this->forma($empresa);
        $produto = $this->produto($empresa, ['ncm' => null]);
        $this->actingAs($this->admin($empresa));

        $this->postJson(route('venda.store'), $this->payload($produto, ['situacao' => 'confirmada']))
            ->assertCreated()
            ->assertJsonMissingPath('nfce');

        $this->assertSame(1, Venda::query()->count());
        $this->assertSame(0, Nfe::query()->count());
    }

    public function test_cpf_invalido_nao_grava_a_venda_nem_consome_numero(): void
    {
        $empresa = $this->empresa(['nfce_emitir_pdv' => true, 'uf' => 'PB']);
        $produto = $this->produto($empresa);
        $this->actingAs($this->admin($empresa));

        $this->postJson(route('venda.store'), $this->payload($produto, [
            'situacao' => 'confirmada',
            'nfce_documento' => '111.111.111-11',
        ]))->assertStatus(422)->assertJsonValidationErrors('nfce_documento');

        $this->assertSame(0, Venda::query()->count());
        $this->assertSame(0, Nfe::query()->count());
        $this->assertSame(0, NfeNumeracao::query()->where('modelo', Nfe::MODELO_NFCE)->count());
    }

    public function test_item_sem_ncm_nao_grava_a_venda_quando_a_flag_esta_ligada(): void
    {
        $empresa = $this->empresaComCertificado(['nfce_emitir_pdv' => true]);
        $produto = $this->produto($empresa, ['ncm' => null]);
        $this->actingAs($this->admin($empresa));

        $this->postJson(route('venda.store'), $this->payload($produto, ['situacao' => 'confirmada']))
            ->assertStatus(422);

        $this->assertSame(0, Venda::query()->count());
        $this->assertSame(0, Nfe::query()->count());
    }

    public function test_sefaz_indisponivel_grava_a_venda_e_nao_consome_numero(): void
    {
        app()->instance('nfe.sefaz.status', fn () => 'A SEFAZ está indisponível. Nenhum número foi usado.');

        $empresa = $this->empresaComCertificado(['nfce_emitir_pdv' => true, 'nfe_sequencia' => 4]);
        $this->forma($empresa);
        $produto = $this->produto($empresa);
        $this->actingAs($this->admin($empresa));

        $resposta = $this->postJson(route('venda.store'), $this->payload($produto, [
            'situacao' => 'confirmada',
            'forma_pagamento' => 'Dinheiro',
            'valor_recebido' => '10,00',
        ]))->assertCreated();

        $resposta->assertJsonPath('nfce.ok', false);
        $this->assertStringContainsString('SEFAZ', (string) $resposta->json('nfce.mensagem'));
        $this->assertSame(1, Venda::query()->count());
        $this->assertNull(Venda::query()->first()->nfe_numero);
        $this->assertSame(0, Nfe::query()->count());
        $this->assertSame(4, $empresa->fresh()->nfe_sequencia);
        $this->assertSame(0, NfeNumeracao::query()->where('modelo', Nfe::MODELO_NFCE)->count());
    }

    public function test_venda_com_nfe_autorizada_nao_emite_nfce(): void
    {
        app()->instance('nfe.sefaz.status', fn () => null);

        $empresa = $this->empresaComCertificado(['nfce_emitir_pdv' => true]);
        $produto = $this->produto($empresa);
        $venda = Venda::create([
            'empresa_id' => $empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 10,
            'total' => 10,
            'forma_pagamento' => 'Dinheiro',
            'situacao' => 'confirmada',
        ]);
        VendaItem::create([
            'venda_id' => $venda->id,
            'produto_id' => $produto->id,
            'produto_nome' => 'Produto',
            'quantidade' => 1,
            'preco_unitario' => 10,
            'desconto' => 0,
            'total' => 10,
            'ncm' => '61091000',
        ]);
        Nfe::create([
            'empresa_id' => $empresa->id,
            'venda_id' => $venda->id,
            'modelo' => Nfe::MODELO_NFE,
            'numero' => '000000001',
            'serie' => '001',
            'situacao' => 'autorizada',
            'ambiente' => 2,
        ]);
        $this->actingAs($this->admin($empresa));

        $resultado = app(NfceService::class)->emitir($venda->fresh('itens'));

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('NF-e autorizada', $resultado['mensagem']);
        $this->assertSame(1, Nfe::query()->count());
        $this->assertNull($venda->fresh()->nfe_numero);
    }

    public function test_numeracao_da_nfce_nao_avanca_a_sequencia_da_nfe(): void
    {
        $empresa = $this->empresa(['nfe_sequencia' => 6, 'nfce_serie' => '001']);

        $numero = app(EmpresaService::class)->proximoNumeronNfe($empresa, Nfe::MODELO_NFCE);

        $this->assertSame('000000001', $numero);
        $this->assertSame(6, $empresa->fresh()->nfe_sequencia);
        $this->assertSame(1, NfeNumeracao::query()->where('modelo', Nfe::MODELO_NFCE)->value('ultimo_numero'));
        $this->assertNull(NfeNumeracao::query()->where('modelo', Nfe::MODELO_NFE)->value('ultimo_numero'));
    }

    public function test_xml_da_nfce_nao_usa_cpf_zerado_e_informa_o_troco(): void
    {
        $empresa = $this->empresaComCertificado();
        $produto = $this->produto($empresa);
        $venda = Venda::create([
            'empresa_id' => $empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'subtotal' => 10,
            'total' => 10,
            'forma_pagamento' => 'Dinheiro',
            'situacao' => 'confirmada',
            'natureza_operacao' => 'VENDA DE MERCADORIA',
            'mod_frete' => 9,
        ]);
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

        $xml = (new NfceBuilderService($empresa))
            ->buildAndSignNfce($venda->fresh(['itens.produto.unidadeMedida', 'cliente']), '000000001', 20, null);

        $this->assertStringContainsString('<mod>65</mod>', $xml);
        $this->assertStringContainsString('<tpImp>4</tpImp>', $xml);
        $this->assertStringNotContainsString('00000000000', $xml);
        $this->assertStringContainsString('<vTroco>10.00</vTroco>', $xml);
        $this->assertStringContainsString('<vPag>20.00</vPag>', $xml);
        $this->assertNull($venda->fresh()->nfe_numero);
    }

    public function test_documento_do_consumidor_rejeita_digito_invalido(): void
    {
        $this->assertTrue(NfceBuilderService::documentoValido('52998224725'));
        $this->assertFalse(NfceBuilderService::documentoValido('52998224726'));
        $this->assertFalse(NfceBuilderService::documentoValido('11111111111'));
    }

    private function empresa(array $dados = []): Empresa
    {
        return Empresa::create(array_merge([
            'razao_social' => 'Empresa Teste LTDA',
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

    private function forma(Empresa $empresa): void
    {
        FormaPagamento::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Dinheiro',
            'tipo' => 'avista',
            'gera_conta_receber' => false,
            'ativo' => true,
        ]);
    }

    private function produto(Empresa $empresa, array $dados = []): Produto
    {
        return Produto::create(array_merge([
            'empresa_id' => $empresa->id,
            'nome' => 'Produto',
            'codigo' => 'P1',
            'tipo' => 1,
            'preco_venda' => 10,
            'ncm' => '61091000',
            'controla_estoque' => false,
            'ativo' => true,
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

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Produto $produto, array $extra = []): array
    {
        return array_merge([
            'data_venda' => now()->toDateString(),
            'forma_pagamento' => 'Dinheiro',
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => '1',
                'preco_unitario' => '10,00',
            ]],
        ], $extra);
    }

    private function empresaComCertificado(array $dados = []): Empresa
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
            $this->markTestSkipped('Não foi possível gerar chave de teste.');
        }

        $csr = openssl_csr_new(['commonName' => 'EMPRESA TESTE:11222333000181'], $chave, $config);
        $x509 = openssl_csr_sign($csr, null, $chave, 30, $config);
        openssl_pkcs12_export($x509, $pfx, $chave, 'teste123');

        $relativo = 'certificados/teste_nfce_'.uniqid().'.pfx';
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
