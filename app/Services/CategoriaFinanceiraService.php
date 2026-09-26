<?php

namespace App\Services;

use App\Models\CategoriaFinanceira;
use App\Repositories\CategoriaFinanceiraRepository;
use Illuminate\Http\Request;

class CategoriaFinanceiraService
{
    public function __construct(
        protected CategoriaFinanceiraRepository $repository
    ) {}

    public function index(Request $request)
    {
        $categorias = $this->repository->index($request);

        return view('categoria-financeira.index', [
            'categorias' => $categorias,
            'busca'      => $request->get('busca'),
        ]);
    }

    public function create()
    {
        return view('categoria-financeira.create');
    }

    public function store($request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('categoria-financeira.index')
            ->with('success', 'Categoria financeira cadastrada com sucesso.');
    }

    public function edit(CategoriaFinanceira $categoriaFinanceira)
    {
        if ((int) $categoriaFinanceira->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        return view('categoria-financeira.edit', [
            'categoriaFinanceira' => $categoriaFinanceira,
        ]);
    }

    public function update($request, CategoriaFinanceira $categoriaFinanceira)
    {
        if ((int) $categoriaFinanceira->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        $this->repository->update($categoriaFinanceira, $request->validated());

        return redirect()
            ->route('categoria-financeira.index')
            ->with('success', 'Categoria financeira editada com sucesso.');
    }

    public function destroy($id)
    {
        $this->repository->destroy($id);

        return redirect()->route('categoria-financeira.index')
            ->with('success', 'Categoria financeira apagada com sucesso.');
    }
}
