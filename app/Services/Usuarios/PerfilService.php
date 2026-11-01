<?php

namespace App\Services\Usuarios;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use App\Support\CatalogoPermissoes;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PerfilService
{
    public function garantir(Empresa $empresa): void
    {
        $padroes = [
            'admin' => 'Administrador',
            'financeiro' => 'Financeiro',
            'vendedor' => 'Vendedor',
        ];

        foreach ($padroes as $slug => $nome) {
            Perfil::firstOrCreate(
                ['empresa_id' => $empresa->id, 'slug' => $slug],
                [
                    'nome' => $nome,
                    'permissoes' => CatalogoPermissoes::padrao($slug),
                    'sistema' => true,
                    'ativo' => true,
                ]
            );
        }
    }

    public function index()
    {
        $empresaId = (int) auth()->user()->empresa_id;
        $this->garantirEmpresaAtual();

        $perfis = Perfil::where('empresa_id', $empresaId)->orderBy('nome')->get();
        $contagem = User::where('empresa_id', $empresaId)
            ->selectRaw('perfil, COUNT(*) as total')
            ->groupBy('perfil')
            ->pluck('total', 'perfil');

        return view('permissao.index', [
            'perfis' => $perfis,
            'contagem' => $contagem,
        ]);
    }

    public function create()
    {
        return view('permissao.create', [
            'perfil' => new Perfil(['ativo' => true, 'permissoes' => ['vendas']]),
        ]);
    }

    public function store(Request $request)
    {
        $empresaId = (int) auth()->user()->empresa_id;
        $nome = trim((string) $request->input('nome'));
        $slug = Str::limit(Str::slug($nome), 50, '');

        if ($slug === '') {
            return back()->withErrors(['nome' => 'Informe um nome válido.'])->withInput();
        }

        if ($slug === 'operador' || Perfil::where('empresa_id', $empresaId)->where('slug', $slug)->exists() || $this->nomeEmUso($empresaId, $nome)) {
            return back()->withErrors(['nome' => 'Já existe um perfil com este nome.'])->withInput();
        }

        Perfil::create([
            'empresa_id' => $empresaId,
            'slug' => $slug,
            'nome' => $nome,
            'permissoes' => $this->permissoesValidas($request),
            'sistema' => false,
            'ativo' => $request->boolean('ativo', true),
        ]);

        return redirect()->route('permissao.index')
            ->with('success', 'Perfil criado com sucesso.');
    }

    public function edit(Perfil $perfil)
    {
        return view('permissao.edit', ['perfil' => $perfil]);
    }

    public function update(Request $request, Perfil $perfil)
    {
        $nome = trim((string) $request->input('nome'));
        if ($nome === '') {
            return back()->withErrors(['nome' => 'Informe um nome válido.'])->withInput();
        }

        $nome = $perfil->slug === 'admin' ? 'Administrador' : $nome;
        if ($this->nomeEmUso((int) $perfil->empresa_id, $nome, (int) $perfil->id)) {
            return back()->withErrors(['nome' => 'Já existe um perfil com este nome.'])->withInput();
        }

        $dados = [
            'nome' => $nome,
            'ativo' => $perfil->slug === 'admin' ? true : $request->boolean('ativo', true),
            'permissoes' => $perfil->slug === 'admin'
                ? CatalogoPermissoes::chaves()
                : $this->permissoesValidas($request),
        ];

        $perfil->update($dados);

        return redirect()->route('permissao.index')
            ->with('success', 'Permissões atualizadas.');
    }

    public function destroy(Perfil $perfil)
    {
        if ($perfil->sistema) {
            return redirect()->route('permissao.index')
                ->with('error', 'Perfil padrão não pode ser excluído.');
        }

        $slugs = $perfil->slug === 'vendedor' ? ['vendedor', 'operador'] : [$perfil->slug];
        $emUso = User::where('empresa_id', $perfil->empresa_id)->whereIn('perfil', $slugs)->exists();
        if ($emUso) {
            return redirect()->route('permissao.index')
                ->with('error', 'Este perfil está em uso e não pode ser excluído.');
        }

        $perfil->delete();

        return redirect()->route('permissao.index')
            ->with('success', 'Perfil excluído.');
    }

    private function garantirEmpresaAtual(): void
    {
        $empresaId = (int) (auth()->user()->empresa_id ?? 0);
        if ($empresaId === 0) {
            return;
        }

        $empresa = auth()->user()->relationLoaded('empresa')
            ? auth()->user()->empresa
            : Empresa::query()->whereKey($empresaId)->first(['id']);

        if ($empresa) {
            $this->garantir($empresa);
        }
    }

    private function nomeEmUso(int $empresaId, string $nome, ?int $ignorarId = null): bool
    {
        $alvo = mb_strtolower($nome);

        return Perfil::query()
            ->where('empresa_id', $empresaId)
            ->when($ignorarId, fn ($query) => $query->whereKeyNot($ignorarId))
            ->pluck('nome')
            ->contains(fn (string $existente) => mb_strtolower($existente) === $alvo);
    }

    /**
     * @return list<string>
     */
    private function permissoesValidas(Request $request): array
    {
        $informadas = $request->input('permissoes', []);
        if (! is_array($informadas)) {
            return [];
        }

        return array_values(array_unique(array_intersect(CatalogoPermissoes::chaves(), $informadas)));
    }
}
