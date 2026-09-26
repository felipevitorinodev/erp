<?php

namespace App\Repositories;

use App\Models\Fornecedor;
use Illuminate\Http\Request;

class FornecedorRepository
{
    public function index(?Request $request = null)
    {
        $query = Fornecedor::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome');

        if ($request && $request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('cnpj', 'like', "%{$busca}%")
                    ->orWhere('cpf', 'like', "%{$busca}%");
            });
        }

        return $query->paginate(20);
    }

    public function store(array $data)
    {
        $data['empresa_id'] = auth()->user()->empresa_id;
        return Fornecedor::create($data);
    }

    public function update(Fornecedor $fornecedor, array $data)
    {
        $fornecedor->update($data);

        return $fornecedor;
    }

    public function destroy($id)
    {
        $fornecedor = Fornecedor::where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);
        $fornecedor->delete();

        return $fornecedor;
    }
}
