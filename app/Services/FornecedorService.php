<?php

namespace App\Services;

use App\Http\Requests\FornecedorRequest;
use App\Models\Fornecedor;
use App\Repositories\FornecedorRepository;

class FornecedorService
{
    public function __construct(
        protected FornecedorRepository $repository
    ) {}

    public function index()
    {
        $fornecedores = $this->repository->index();

        return view('fornecedor.index', ['fornecedores' => $fornecedores]);
    }

    public function create()
    {
        return view('fornecedor.create');
    }

    public function store($request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('fornecedor.index')
            ->with('success', 'Fornecedor cadastrado com sucesso.');
    }

    public function edit(Fornecedor $fornecedor)
    {
        return view('fornecedor.edit', [
            'fornecedor' => $fornecedor
        ]);
    }

    public function update(FornecedorRequest $request, Fornecedor $fornecedor)
    {
        $this->repository->update(
            $fornecedor,
            $request->validated()
        );

        return redirect()
            ->route('fornecedor.index')
            ->with('success', 'Fornecedor editado com sucesso.');
    }

    public function destroy($id)
    {
        $this->repository->destroy($id);

        return redirect()->route('fornecedor.index')
            ->with('success', 'Fornecedor apagado com sucesso.');
    }
}