<?php

namespace App\Services;

use App\Models\Grupo;
use App\Repositories\GrupoRepository;

class GrupoService
{
    public function __construct(
        protected GrupoRepository $repository
    ) {}

    public function index()
    {
        $grupos = $this->repository->index();

        return view('grupo.index', ['grupos' => $grupos]);
    }

    public function create()
    {

        $gruposPrincipais = $this->repository->principais();

        return view('grupo.create',  ['gruposPrincipais' => $gruposPrincipais]);
    }

    public function store($request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('grupo.index')
            ->with('success', 'Grupo cadastrado com sucesso.');
    }

    public function edit(Grupo $grupo)
    {

    $gruposPrincipais = $this->repository->principais();

        return view('grupo.edit', [
            'grupo' => $grupo,
            'gruposPrincipais' => $gruposPrincipais
        ]);
    }

    public function update($request, Grupo $grupo)
    {
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