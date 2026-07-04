<?php

namespace App\Services;

use App\Models\UnidadeMedida;
use App\Repositories\UnidadeMedidaRepository;

class UnidadeMedidaService
{
    public function __construct(
        protected UnidadeMedidaRepository $repository
    ) {}

    public function index()
    {
        $unidadesMedida = $this->repository->index();

        return view('unidadeMedida.index', ['unidadesMedida' => $unidadesMedida]);
    }

    public function create()
    {
        return view('unidadeMedida.create');
    }

    public function store($request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('unidadeMedida.index')
            ->with('success', 'Unidade de medida cadastrada com sucesso.');
    }

    public function edit(UnidadeMedida $unidadeMedida)
    {
        return view('unidadeMedida.edit', ['unidadeMedida' => $unidadeMedida]);
    }

    public function update($request, UnidadeMedida $unidadeMedida)
    {
        $this->repository->update($unidadeMedida, $request->validated());

        return redirect()
            ->route('unidadeMedida.index')
            ->with('success', 'Unidade de medida editada com sucesso.');
    }

    public function destroy($id)
    {
        $this->repository->destroy($id);

        return redirect()->route('unidadeMedida.index')
            ->with('success', 'Unidade de medida apagada com sucesso.');
    }
}