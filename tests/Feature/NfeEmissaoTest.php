<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Nfe;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use App\Services\NfeBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
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

    private function vendaConfirmada(Empresa $empresa): Venda
    {
        return Venda::create([
            'empresa_id' => $empresa->id,
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
        $venda->setRelation('cliente', null);

        $xml = (new NfeBuilderService($empresa))->buildAndSign($venda);
        $dom = simplexml_load_string($xml);
        $dom->registerXPathNamespace('n', 'http://www.portalfiscal.inf.br/nfe');
        $tot = $dom->xpath('//n:ICMSTot')[0];

        $this->assertSame('20.00', (string) $tot->vProd);
        $this->assertSame('3.00', (string) $tot->vDesc);
        $this->assertSame('17.00', (string) $tot->vNF);
        $this->assertSame('17.00', (string) $dom->xpath('//n:detPag/n:vPag')[0]);
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
