<?php

namespace App\Services;

use App\Models\Grupo;
use App\Models\Produto;
use App\Repositories\ProdutoRepository;
use App\Models\UnidadeMedida;
use App\Models\Fornecedor;

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
        return view('produto.create', $this->dadosParaForm());
    }

    public function store($request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('produto.index')
            ->with('success', 'Produto cadastrado com sucesso.');
    }

    public function edit(Produto $produto)
    {
        return view('produto.edit', array_merge(
            ['produto' => $produto],
            $this->dadosParaForm()
        ));
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

    protected function dadosParaForm(): array
    {
        return [
            'grupos'   => Grupo::where('ativo', true)->orderBy('nome')->get(),
            'unidades'     => UnidadeMedida::where('ativo', true)->orderBy('nome')->get(),
            'fornecedores' => Fornecedor::orderBy('nome')->get(),
        ];
    }
}