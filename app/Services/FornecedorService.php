<?php

namespace App\Services;

use App\Http\Requests\FornecedorRequest;
use App\Models\Fornecedor;
use App\Repositories\FornecedorRepository;
use Illuminate\Http\Request;

class FornecedorService
{
    public function __construct(
        protected FornecedorRepository $repository
    ) {}

    public function index(Request $request)
    {
        $fornecedores = $this->repository->index($request);

        return view('fornecedor.index', [
            'fornecedores' => $fornecedores,
            'filtros'      => $request->only(['busca']),
        ]);
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
        if ((int) $fornecedor->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        return view('fornecedor.edit', [
            'fornecedor' => $fornecedor
        ]);
    }

    public function update(FornecedorRequest $request, Fornecedor $fornecedor)
    {
        if ((int) $fornecedor->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

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