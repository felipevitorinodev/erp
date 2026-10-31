<?php

namespace App\Services\Empresas;

use App\Http\Requests\Empresas\EmpresaRequest;
use App\Models\Empresas\Empresa;
use App\Models\Nfes\Nfe;
use App\Repositories\Empresas\EmpresaRepository;
use App\Repositories\Nfes\NfeNumeracaoRepository;
use App\Support\MensagemPublica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use NFePHP\Common\Certificate;
use RuntimeException;
use Throwable;

class EmpresaService
{
    public function __construct(
        protected EmpresaRepository $empresaRepository,
        protected NfeNumeracaoRepository $numeracao,
    ) {}

    public function index(Request $request)
    {
        $empresas = $this->empresaRepository->index($request);

        return view('empresa.index', [
            'empresas' => $empresas,
            'filtros' => $request->only(['busca']),
        ]);
    }

    public function create()
    {
        if (auth()->user()->empresa_id) {
            return redirect()->route('empresa.index');
        }

        return view('empresa.create');
    }

    public function store($request)
    {
        $user = auth()->user();

        if ($user->empresa_id) {
            return redirect()->route('empresa.index')
                ->with('error', 'Você já possui uma empresa vinculada.');
        }

        $dados = $request->validated();
        unset($dados['sem_cnpj']);

        $empresa = $this->empresaRepository->store($dados);
        $user->empresa_id = $empresa->id;
        $user->save();

        return redirect()->route('empresa.index')
            ->with('success', 'Empresa cadastrada com sucesso.');
    }

    public function edit(Empresa $empresa)
    {
        $this->autorizarEmpresa($empresa);

        return view('empresa.edit', [
            'empresa' => $empresa,
        ]);
    }

    public function update(EmpresaRequest $request, Empresa $empresa)
    {
        $this->autorizarEmpresa($empresa);

        $dados = $request->validated();
        unset(
            $dados['certificado_pfx'],
            $dados['certificado_senha'],
            $dados['confirmar_ambiente'],
            $dados['nfce_csc_homologacao'],
            $dados['nfce_id_csc_homologacao'],
            $dados['nfce_csc_producao'],
            $dados['nfce_id_csc_producao'],
            $dados['sem_cnpj'],
        );

        if (filled($dados['nfce_serie'] ?? null)) {
            $dados['nfce_serie'] = str_pad((string) $dados['nfce_serie'], 3, '0', STR_PAD_LEFT);
        }

        if (isset($dados['nfe_ambiente'])
            && (int) $dados['nfe_ambiente'] !== (int) ($empresa->nfe_ambiente ?: 2)
            && ! $request->boolean('confirmar_ambiente')) {
            return back()
                ->withInput($request->except(Empresa::segredosForaDaSessao()))
                ->withErrors(['confirmar_ambiente' => 'Marque a confirmação para alterar o ambiente da NF-e.']);
        }

        try {
            $this->aplicarCertificado($request, $empresa, $dados);
        } catch (RuntimeException $e) {
            return back()
                ->withInput($request->except(Empresa::segredosForaDaSessao()))
                ->with('error', MensagemPublica::de($e));
        }

        $this->aplicarCscNfce($request, $empresa);
        $this->empresaRepository->update($empresa, $dados);
        $this->alinharSequenciaExibida($empresa);

        return redirect()
            ->route('empresa.index')
            ->with('success', 'Empresa editada com sucesso.');
    }

    public function proximoNumeronNfe(Empresa $empresa, int $modelo = Nfe::MODELO_NFE): string
    {
        return $this->numeracao->proximo($empresa, $modelo);
    }

    private function alinharSequenciaExibida(Empresa $empresa): void
    {
        $this->numeracao->alinharSequenciaExibida($empresa);
    }

    private function aplicarCertificado(EmpresaRequest $request, Empresa $empresa, array &$dados): void
    {
        $senhaInformada = trim((string) $request->input('certificado_senha', ''));
        $temArquivo = $request->hasFile('certificado_pfx');

        if (! $temArquivo && $senhaInformada === '') {
            return;
        }

        $senha = $senhaInformada !== ''
            ? $senhaInformada
            : $empresa->certificado_senha_decrypted;

        if ($senha === null || $senha === '') {
            throw new RuntimeException('Certificado inválido ou senha incorreta.');
        }

        if ($temArquivo) {
            $conteudo = file_get_contents($request->file('certificado_pfx')->getRealPath());
            $cnpjInformado = array_key_exists('cnpj', $dados) ? $dados['cnpj'] : $empresa->cnpj;
            $cnpj = preg_replace('/\D/', '', (string) $cnpjInformado) ?? '';

            if (strlen($cnpj) !== 14) {
                throw new RuntimeException('Informe o CNPJ da empresa antes de enviar o certificado.');
            }

            $certificado = $this->validarPfx($conteudo, $senha);

            if ($certificado->isExpired()) {
                throw new RuntimeException('O certificado enviado está vencido desde '.$certificado->getValidTo()->format('d/m/Y').'.');
            }

            $cnpjCertificado = preg_replace('/\D/', '', (string) $certificado->getCnpj());
            if ($cnpjCertificado !== '' && $cnpjCertificado !== $cnpj) {
                throw new RuntimeException('O certificado enviado pertence a outro CNPJ ('.$cnpjCertificado.').');
            }

            $caminho = 'certificados/'.$empresa->id.'_'.$cnpj.'.pfx';
            $this->gravarCertificado($caminho, $conteudo);

            if ($empresa->certificado_path && $empresa->certificado_path !== $caminho) {
                $anterior = storage_path('app/'.$empresa->certificado_path);
                if (is_file($anterior)) {
                    File::delete($anterior);
                }
            }

            $dados['certificado_path'] = $caminho;
        } elseif ($empresa->certificado_path) {
            $arquivo = storage_path('app/'.$empresa->certificado_path);
            if (! is_file($arquivo)) {
                throw new RuntimeException('Certificado inválido ou senha incorreta.');
            }

            $this->validarPfx(file_get_contents($arquivo), $senha);
        }

        if ($senhaInformada !== '') {
            $dados['certificado_senha'] = Crypt::encryptString($senhaInformada);
        }
    }

    private function aplicarCscNfce(EmpresaRequest $request, Empresa $empresa): void
    {
        foreach (['homologacao', 'producao'] as $ambiente) {
            $csc = trim((string) $request->input('nfce_csc_'.$ambiente, ''));
            $id = preg_replace('/\D/', '', (string) $request->input('nfce_id_csc_'.$ambiente, '')) ?? '';

            if ($csc !== '') {
                $empresa->{'nfce_csc_'.$ambiente} = Crypt::encryptString($csc);
            }

            if ($id !== '') {
                $empresa->{'nfce_id_csc_'.$ambiente} = str_pad($id, 6, '0', STR_PAD_LEFT);
            }
        }
    }

    private function validarPfx(string|false $conteudo, string $senha): Certificate
    {
        if ($conteudo === false || $conteudo === '') {
            throw new RuntimeException('Certificado inválido ou senha incorreta.');
        }

        try {
            return Certificate::readPfx($conteudo, $senha);
        } catch (Throwable) {
            throw new RuntimeException('Certificado inválido ou senha incorreta.');
        }
    }

    private function autorizarEmpresa(Empresa $empresa): void
    {
        if ((int) $empresa->id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }

    private function gravarCertificado(string $caminhoRelativo, string $conteudo): void
    {
        $diretorio = storage_path('app/certificados');
        File::ensureDirectoryExists($diretorio);
        File::put(storage_path('app/'.$caminhoRelativo), $conteudo);
    }

    public function destroy($id)
    {
        return redirect()->route('empresa.index')
            ->with('error', 'A empresa em uso não pode ser excluída pelo sistema. Entre em contato com o suporte.');
    }
}
