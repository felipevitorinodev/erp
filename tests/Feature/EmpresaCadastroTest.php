<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ViewErrorBag;
use NFePHP\Common\Certificate;
use Tests\TestCase;

class EmpresaCadastroTest extends TestCase
{
    use RefreshDatabase;

    private const CNPJ = '11.222.333/0001-81';

    private const OUTRO_CNPJ = '11.444.777/0001-61';

    private const AVISO_CNPJ = 'Já existe um certificado digital para o CNPJ atual. Envie o certificado do novo CNPJ no mesmo salvamento.';

    /** @var list<string> */
    private array $arquivosCriados = [];

    protected function tearDown(): void
    {
        foreach ($this->arquivosCriados as $arquivo) {
            if (is_file($arquivo)) {
                File::delete($arquivo);
            }
        }

        parent::tearDown();
    }

    public function test_trocar_cnpj_com_certificado_exige_o_arquivo_novo(): void
    {
        $empresa = $this->empresa();
        $caminho = $this->caminho($empresa, '11222333000181');
        $empresa->update(['certificado_path' => $caminho]);

        $this->actingAs($this->admin($empresa))
            ->from(route('empresa.edit', $empresa))
            ->put(route('empresa.update', $empresa), $this->dados([
                'cnpj' => self::OUTRO_CNPJ,
            ]))
            ->assertRedirect(route('empresa.edit', $empresa))
            ->assertSessionHas('error', self::AVISO_CNPJ);

        $empresa->refresh();
        $this->assertSame('11222333000181', $empresa->cnpj);
        $this->assertSame($caminho, $empresa->certificado_path);
    }

    public function test_marcar_sem_cnpj_com_certificado_mantem_o_documento(): void
    {
        $empresa = $this->empresa();
        $caminho = $this->caminho($empresa, '11222333000181');
        $empresa->update(['certificado_path' => $caminho]);

        $this->actingAs($this->admin($empresa))
            ->put(route('empresa.update', $empresa), $this->dados([
                'sem_cnpj' => '1',
            ]))
            ->assertSessionHas('error', self::AVISO_CNPJ);

        $empresa->refresh();
        $this->assertSame('11222333000181', $empresa->cnpj);
        $this->assertSame($caminho, $empresa->certificado_path);
    }

    public function test_trocar_cnpj_sem_certificado_continua_permitido(): void
    {
        $empresa = $this->empresa();

        $this->actingAs($this->admin($empresa))
            ->put(route('empresa.update', $empresa), $this->dados([
                'cnpj' => self::OUTRO_CNPJ,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(self::OUTRO_CNPJ, $empresa->fresh()->cnpj);
    }

    public function test_editar_outros_campos_nao_reabre_o_certificado(): void
    {
        $empresa = $this->empresa();
        $caminho = $this->caminho($empresa, '11222333000181');
        $empresa->update(['certificado_path' => $caminho]);

        $this->actingAs($this->admin($empresa))
            ->put(route('empresa.update', $empresa), $this->dados([
                'razao_social' => 'Empresa Atualizada',
                'telefone' => '8333334444',
            ]))
            ->assertSessionHasNoErrors();

        $empresa->refresh();
        $this->assertSame('Empresa Atualizada', $empresa->razao_social);
        $this->assertSame('8333334444', $empresa->telefone);
        $this->assertSame($caminho, $empresa->certificado_path);
        $this->assertFileDoesNotExist(storage_path('app/'.$caminho));
    }

    public function test_trocar_cnpj_com_pfx_novo_grava_os_dois(): void
    {
        $empresa = $this->empresa();
        $senha = 'senha-teste';
        $anterior = $this->reservar($this->caminho($empresa, '11222333000181'));
        $novo = $this->reservar($this->caminho($empresa, '11444777000161'));
        File::ensureDirectoryExists(dirname($anterior));
        File::put($anterior, 'certificado-anterior');
        $empresa->update([
            'certificado_path' => $this->caminho($empresa, '11222333000181'),
            'certificado_senha' => Crypt::encryptString($senha),
        ]);

        $this->actingAs($this->admin($empresa))
            ->put(route('empresa.update', $empresa), $this->dados([
                'cnpj' => self::OUTRO_CNPJ,
                'certificado_senha' => $senha,
                'certificado_pfx' => UploadedFile::fake()->createWithContent(
                    'certificado.pfx',
                    $this->pfx($senha, 365)
                ),
            ]))
            ->assertSessionHasNoErrors();

        $empresa->refresh();
        $this->assertSame(self::OUTRO_CNPJ, $empresa->cnpj);
        $this->assertSame($this->caminho($empresa, '11444777000161'), $empresa->certificado_path);
        $this->assertFileDoesNotExist($anterior);
        $this->assertFileExists($novo);
        $this->assertNotSame('certificado-anterior', File::get($novo));
    }

    public function test_senha_de_certificado_vencido_e_recusada(): void
    {
        $empresa = $this->empresa();
        $senha = 'senha-teste';
        $pfx = $this->pfx($senha, 0);
        $this->aguardarVencimento($pfx, $senha);
        $arquivo = $this->reservar($this->caminho($empresa, '11222333000181'));
        File::ensureDirectoryExists(dirname($arquivo));
        File::put($arquivo, $pfx);
        $cifrada = Crypt::encryptString($senha);
        $empresa->update([
            'certificado_path' => $this->caminho($empresa, '11222333000181'),
            'certificado_senha' => $cifrada,
        ]);

        $this->actingAs($this->admin($empresa))
            ->put(route('empresa.update', $empresa), $this->dados([
                'certificado_senha' => $senha,
            ]))
            ->assertSessionHas('error');

        $erro = session('error');
        $this->assertIsString($erro);
        $this->assertStringContainsString('está vencido desde', $erro);

        $empresa->refresh();
        $this->assertSame($cifrada, $empresa->certificado_senha);
        $this->assertSame($this->caminho($empresa, '11222333000181'), $empresa->certificado_path);
    }

    public function test_senha_de_certificado_valido_e_atualizada(): void
    {
        $empresa = $this->empresa();
        $arquivo = $this->reservar($this->caminho($empresa, '11222333000181'));
        File::ensureDirectoryExists(dirname($arquivo));
        File::put($arquivo, $this->pfx('senha-teste', 365));
        $empresa->update([
            'certificado_path' => $this->caminho($empresa, '11222333000181'),
            'certificado_senha' => Crypt::encryptString('senha-errada'),
        ]);

        $this->actingAs($this->admin($empresa))
            ->put(route('empresa.update', $empresa), $this->dados([
                'certificado_senha' => 'senha-teste',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('senha-teste', $empresa->fresh()->certificado_senha_decrypted);
    }

    public function test_erro_de_validacao_devolve_o_que_foi_digitado(): void
    {
        $empresa = $this->empresa();
        $usuario = $this->admin($empresa);
        $digitado = [
            'razao_social' => 'Razao Digitada Unica',
            'nome_fantasia' => 'Fantasia Digitada',
            'ie' => 'IE-DIGITADA',
            'im' => 'IM-DIGITADA',
            'telefone' => '8333334444',
            'celular' => '83988887777',
            'email' => 'digitado@empresa.test',
            'logradouro' => 'Rua Digitada',
            'numero' => '123A',
            'complemento' => 'Sala Digitada',
            'cidade' => 'Cidade Digitada',
            'uf' => 'SP',
            'cod_ibge' => '2507507',
            'cnpj' => '11.111.111/1111-11',
        ];

        $this->actingAs($usuario)
            ->from(route('empresa.edit', $empresa))
            ->put(route('empresa.update', $empresa), $digitado)
            ->assertRedirect(route('empresa.edit', $empresa))
            ->assertSessionHasErrors('cnpj');

        $this->assertSame('Empresa Teste LTDA', $empresa->fresh()->razao_social);

        $resposta = $this->actingAs($usuario)
            ->get(route('empresa.edit', $empresa));

        $resposta->assertOk();
        foreach ([
            'Razao Digitada Unica',
            'Fantasia Digitada',
            'IE-DIGITADA',
            'IM-DIGITADA',
            '8333334444',
            '83988887777',
            'digitado@empresa.test',
            'Rua Digitada',
            '123A',
            'Sala Digitada',
            'Cidade Digitada',
        ] as $texto) {
            $resposta->assertSee($texto, false);
        }
        $resposta->assertSee('value="SP" selected', false);
    }

    public function test_limites_dos_campos_acompanham_as_colunas(): void
    {
        $empresa = $this->empresa();
        $usuario = $this->admin($empresa);

        $this->actingAs($usuario)
            ->put(route('empresa.update', $empresa), $this->dados([
                'razao_social' => str_repeat('A', 151),
            ]))
            ->assertSessionHasErrors('razao_social');
        $this->assertSame('Empresa Teste LTDA', $empresa->fresh()->razao_social);

        foreach ([
            'email' => str_repeat('a', 88).'@empresa.test',
            'logradouro' => str_repeat('L', 151),
            'complemento' => str_repeat('C', 101),
        ] as $campo => $valor) {
            $this->actingAs($usuario)
                ->put(route('empresa.update', $empresa), $this->dados([$campo => $valor]))
                ->assertSessionHasErrors($campo);
            $this->assertNull($empresa->fresh()->{$campo});
        }

        $razao = str_repeat('B', 150);
        $email = str_repeat('a', 87).'@empresa.test';

        $this->actingAs($usuario)
            ->put(route('empresa.update', $empresa), $this->dados([
                'razao_social' => $razao,
                'email' => $email,
                'logradouro' => str_repeat('L', 150),
                'complemento' => str_repeat('C', 100),
            ]))
            ->assertSessionHasNoErrors();

        $empresa->refresh();
        $this->assertSame($razao, $empresa->razao_social);
        $this->assertSame($email, $empresa->email);
        $this->assertSame(str_repeat('L', 150), $empresa->logradouro);
        $this->assertSame(str_repeat('C', 100), $empresa->complemento);
    }

    public function test_celular_e_gravado_e_respeita_o_limite(): void
    {
        $empresa = $this->empresa();
        $usuario = $this->admin($empresa);

        $this->actingAs($usuario)
            ->put(route('empresa.update', $empresa), $this->dados([
                'celular' => '83999998888',
            ]))
            ->assertSessionHasNoErrors();
        $this->assertSame('83999998888', $empresa->fresh()->celular);

        $this->actingAs($usuario)
            ->put(route('empresa.update', $empresa), $this->dados([
                'celular' => str_repeat('1', 21),
            ]))
            ->assertSessionHasErrors('celular');
        $this->assertSame('83999998888', $empresa->fresh()->celular);
    }

    public function test_formularios_limitam_o_tamanho_dos_campos(): void
    {
        $empresa = $this->empresa();
        $edicao = $this->actingAs($this->admin($empresa))
            ->get(route('empresa.edit', $empresa));

        $edicao->assertOk();
        $this->assertLimites($edicao->getContent());

        $this->actingAs($this->admin($empresa));
        view()->share('errors', new ViewErrorBag);
        $this->assertLimites(view('empresa.create')->render());
    }

    private function assertLimites(string $html): void
    {
        $limites = [
            'razao_social' => '150',
            'nome_fantasia' => '150',
            'ie' => '20',
            'im' => '20',
            'email' => '100',
            'celular' => '20',
            'logradouro' => '150',
            'complemento' => '100',
            'cidade' => '100',
        ];

        foreach ($limites as $campo => $maximo) {
            $this->assertMatchesRegularExpression(
                '/name="'.$campo.'"[^>]*maxlength="'.$maximo.'"/',
                $html
            );
        }
    }

    private function empresa(): Empresa
    {
        return Empresa::create([
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => '11222333000181',
            'uf' => 'PB',
            'nfe_ambiente' => 2,
            'nfe_serie' => '001',
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
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function dados(array $extra = []): array
    {
        return array_merge([
            'razao_social' => 'Empresa Teste LTDA',
            'cnpj' => self::CNPJ,
        ], $extra);
    }

    private function caminho(Empresa $empresa, string $cnpj): string
    {
        return 'certificados/'.$empresa->id.'_'.$cnpj.'.pfx';
    }

    private function reservar(string $relativo): string
    {
        $absoluto = storage_path('app/'.$relativo);
        if (is_file($absoluto)) {
            $this->fail('O teste não altera um certificado que já está no disco.');
        }

        $this->arquivosCriados[] = $absoluto;

        return $absoluto;
    }

    private function pfx(string $senha, int $dias): string
    {
        $config = $this->configuracaoOpenSsl();
        $chave = openssl_pkey_new($config);
        $csr = $chave === false
            ? false
            : openssl_csr_new(['commonName' => 'Certificado de teste', 'countryName' => 'BR'], $chave, $config);
        $certificado = $csr === false
            ? false
            : openssl_csr_sign($csr, null, $chave, $dias, $config);
        $pfx = '';
        $exportado = $certificado !== false && openssl_pkcs12_export($certificado, $pfx, $chave, $senha);

        if ($chave === false || $csr === false || $certificado === false || ! $exportado || $pfx === '') {
            $this->fail('OpenSSL não gerou o certificado de teste: '.$this->erroOpenSsl());
        }

        return $pfx;
    }

    /**
     * @return array<string, mixed>
     */
    private function configuracaoOpenSsl(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ];

        $arquivo = getenv('OPENSSL_CONF');
        if (! is_string($arquivo) || ! is_file($arquivo)) {
            foreach ([
                'C:/php/extras/ssl/openssl.cnf',
                '/etc/ssl/openssl.cnf',
                '/etc/pki/tls/openssl.cnf',
                '/usr/lib/ssl/openssl.cnf',
            ] as $candidato) {
                if (is_file($candidato)) {
                    $arquivo = $candidato;
                    break;
                }
            }
        }

        if (is_string($arquivo) && is_file($arquivo)) {
            $config['config'] = $arquivo;
        }

        return $config;
    }

    private function aguardarVencimento(string $pfx, string $senha): void
    {
        $certificado = Certificate::readPfx($pfx, $senha);
        $limite = microtime(true) + 3;

        while (! $certificado->isExpired() && microtime(true) < $limite) {
            usleep(200000);
        }

        if (! $certificado->isExpired()) {
            $this->fail('O certificado de teste com validade zero não venceu a tempo.');
        }
    }

    private function erroOpenSsl(): string
    {
        $erros = [];
        while ($erro = openssl_error_string()) {
            $erros[] = $erro;
        }

        return $erros === [] ? 'sem detalhe do OpenSSL' : implode('; ', $erros);
    }
}
