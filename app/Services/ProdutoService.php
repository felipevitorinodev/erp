<?php

namespace App\Services;

use App\Models\Grupo;
use App\Models\Produto;
use App\Repositories\ProdutoRepository;
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
            'grupos'   => $grupos,
            'filtros'  => $request->only(['busca', 'tipo', 'grupo_id']),
        ]);
    }

    public function create()
    {
        return view('produto.create');
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

        return view('produto.edit', ['produto' => $produto]);
    }

    public function update($request, Produto $produto)
    {
        if ((int) $produto->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        $this->repository->update($produto, $request->validated());

        return redirect()
            ->route('produto.index')
            ->with('success', 'Produto editado com sucesso.');
    }

    public function destroy($id)
    {
        $this->repository->destroy($id);

        return redirect()->route('produto.index')
            ->with('success', 'Produto apagado com sucesso.');
    }
}
