<?php

namespace App\Repositories;

use App\Models\UnidadeMedida;

class UnidadeMedidaRepository
{
    public function index()
    {
        return UnidadeMedida::orderBy('nome')->get();
    }

    public function store(array $data)
    {
        return UnidadeMedida::create($data);
    }

    public function update(UnidadeMedida $unidadeMedida, array $data)
    {
        $unidadeMedida->update($data);

        return $unidadeMedida;
    }

    public function destroy($id)
    {
        $unidadeMedida = UnidadeMedida::findOrFail($id);
        $unidadeMedida->delete();

        return $unidadeMedida;
    }
}