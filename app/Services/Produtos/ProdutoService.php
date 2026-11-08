<?php

namespace App\Services\Produtos;

use App\Models\Grupos\Grupo;
use App\Models\PerfisFiscais\PerfilFiscal;
use App\Models\Produtos\Produto;
use App\Repositories\Produtos\ProdutoRepository;
use App\Support\Auditoria;
use Illuminate\Http\Request;

class ProdutoService
{
    public function __construct(
        protected ProdutoRepository $repository
    ) {}

    public function index(Request $request)
    {
        $produtos = $this->repository->index($request);
        $grupos = Grupo::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('produto.index', [
            'produtos' => $produtos,
            'grupos' => $grupos,
            'filtros' => $request->only(['busca', 'tipo', 'grupo_id']),
        ]);
    }

    public function create()
    {
        return view('produto.create', [
            'perfisFiscais' => $this->perfisFiscais(),
            'proximoCodigo' => $this->repository->proximoCodigo((int) auth()->user()->empresa_id),
        ]);
    }

    public function store($request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('produto.index')
            ->with('success', 'Produto cadastrado com sucesso.');
    }

    public function edit(Produto $produto)
    {
        if ((int) $produto->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        $produto->load(['grupos.parent', 'unidadeMedida', 'fornecedor']);

        return view('produto.edit', [
            'produto' => $produto,
            'perfisFiscais' => $this->perfisFiscais(),
        ]);
    }

    public function update($request, Produto $produto)
    {
        if ((int) $produto->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        $precos = ['preco_custo', 'preco_venda', 'preco_minimo'];
        $antes = array_map('floatval', $produto->only($precos));

        $this->repository->update($produto, $request->validated());

        $depois = array_map('floatval', $produto->fresh()?->only($precos) ?? []);
        if ($depois !== [] && $depois != $antes) {
            Auditoria::registrar('produto.preco_alterado', 'produto', $produto->id, [
                'antes' => $antes,
                'depois' => $depois,
            ]);
        }

        return redirect()
            ->route('produto.index')
            ->with('success', 'Produto editado com sucesso.');
    }

    private function perfisFiscais()
    {
        return PerfilFiscal::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'cfop', 'csosn']);
    }

    public function destroy($id)
    {
        $produto = $this->repository->destroy($id);

        Auditoria::registrar('produto.excluido', 'produto', $produto->id, [
            'codigo' => $produto->codigo,
            'estoque_atual' => (float) $produto->estoque_atual,
        ]);

        return redirect()->route('produto.index')
            ->with('success', 'Produto apagado com sucesso.');
    }
}
