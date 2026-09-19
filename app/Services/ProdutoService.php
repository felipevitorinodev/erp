<?php

namespace App\Services;

use App\Models\Produto;
use App\Repositories\ProdutoRepository;

class ProdutoService
{
    public function __construct(
        protected ProdutoRepository $repository
    ) {}

    public function index()
    {
        $produtos = $this->repository->index();

        return view('produto.index', ['produtos' => $produtos]);
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
        $produto->load(['grupos.parent', 'unidadeMedida', 'fornecedor']);

        return view('produto.edit', ['produto' => $produto]);
    }

    public function update($request, Produto $produto)
    {
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
