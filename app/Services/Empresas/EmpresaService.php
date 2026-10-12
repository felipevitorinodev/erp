<?php

namespace App\Services\Empresas;

use App\Http\Requests\Empresas\EmpresaRequest;
use App\Models\Empresas\Empresa;
use App\Repositories\Empresas\EmpresaRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use NFePHP\Common\Certificate;
use RuntimeException;
use Throwable;

class EmpresaService
{
    public function __construct(
        protected EmpresaRepository $empresaRepository
    ) {}

    public function index(Request $request)
    {
        $empresas = $this->empresaRepository->index($request);

        return view('empresa.index', [
            'empresas' => $empresas,
            'filtros'  => $request->only(['busca']),
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

        $empresa = $this->empresaRepository->store($request->validated());
        $user->empresa_id = $empresa->id;
        $user->save();

        return redirect()->route('empresa.index')
            ->with('success', 'Empresa cadastrada com sucesso.');
    }   

    public function edit(Empresa $empresa)
    {
        return view('empresa.edit', [
            'empresa' => $empresa
        ]);
    }

    public function update(EmpresaRequest $request, Empresa $empresa)
    {
        $dados = $request->validated();
        unset($dados['certificado_pfx'], $dados['certificado_senha']);

        try {
            $this->aplicarCertificado($request, $empresa, $dados);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        $this->empresaRepository->update($empresa, $dados);

        return redirect()
            ->route('empresa.index')
            ->with('success', 'Empresa editada com sucesso.');
    }

    public function proximoNumeronNfe(Empresa $empresa): string
    {
        return DB::transaction(function () use ($empresa) {
            $registro = Empresa::query()
                ->where('id', $empresa->id)
                ->lockForUpdate()
                ->firstOrFail();

            $sequencia = (int) ($registro->nfe_sequencia ?: 1);
            $numero = str_pad((string) $sequencia, 9, '0', STR_PAD_LEFT);

            $registro->nfe_sequencia = $sequencia + 1;
            $registro->save();

            return $numero;
        });
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
            $this->validarPfx($conteudo, $senha);

            $cnpj = preg_replace('/\D/', '', (string) ($dados['cnpj'] ?? $empresa->cnpj));
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

    private function validarPfx(string|false $conteudo, string $senha): void
    {
        if ($conteudo === false || $conteudo === '') {
            throw new RuntimeException('Certificado inválido ou senha incorreta.');
        }

        try {
            Certificate::readPfx($conteudo, $senha);
        } catch (Throwable) {
            throw new RuntimeException('Certificado inválido ou senha incorreta.');
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
        $this->empresaRepository->destroy($id);

        return redirect()->route('empresa.index')
            ->with('success', 'Empresa apagada com sucesso.');
    }                       
}
