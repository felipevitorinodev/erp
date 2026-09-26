<?php

namespace App\Repositories;

use App\Models\UnidadeMedida;
use Illuminate\Http\Request;

class UnidadeMedidaRepository
{
    public function index(?Request $request = null)
    {
        $query = UnidadeMedida::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome');

        if ($request && $request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('sigla', 'like', "%{$busca}%");
            });
        }

        return $query->paginate(20);
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
