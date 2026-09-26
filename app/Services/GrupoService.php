<?php

namespace App\Services;

use App\Models\Grupo;
use App\Repositories\GrupoRepository;
use Illuminate\Http\Request;

class GrupoService
{
    public function __construct(
        protected GrupoRepository $repository
    ) {}

    public function index(Request $request)
    {
        $grupos = $this->repository->index($request);

        return view('grupo.index', [
            'grupos'  => $grupos,
            'filtros' => $request->only(['busca']),
        ]);
    }

    public function create()
    {
        return view('grupo.create');
    }

    public function store($request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('grupo.index')
            ->with('success', 'Grupo cadastrado com sucesso.');
    }

    public function edit(Grupo $grupo)
    {
        if ((int) $grupo->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        $grupo->load('parent');

        return view('grupo.edit', ['grupo' => $grupo]);
    }

    public function update($request, Grupo $grupo)
    {
        if ((int) $grupo->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        $this->repository->update(
            $grupo,
            $request->validated()
        );

        return redirect()
            ->route('grupo.index')
            ->with('success', 'Grupo editado com sucesso.');
    }

    public function destroy($id)
    {
        $this->repository->destroy($id);

        return redirect()->route('grupo.index')
            ->with('success', 'Grupo apagado com sucesso.');
    }
}