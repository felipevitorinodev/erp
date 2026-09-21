<?php

namespace App\Repositories;

use App\Models\UnidadeMedida;

class UnidadeMedidaRepository
{
    public function index()
    {
        return UnidadeMedida::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome')
            ->get();
    }

    public function store(array $data)
    {
        $data['empresa_id'] = auth()->user()->empresa_id;
        return UnidadeMedida::create($data);
    }

    public function update(UnidadeMedida $unidadeMedida, array $data)
    {
        $unidadeMedida->update($data);

        return $unidadeMedida;
    }

    public function destroy($id)
    {
        $unidadeMedida = UnidadeMedida::where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);
        $unidadeMedida->delete();

        return $unidadeMedida;
    }
}