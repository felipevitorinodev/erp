<?php

namespace App\Repositories;

use App\Models\Fornecedor;

class FornecedorRepository
{
    public function index()
    {
        return Fornecedor::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome')
            ->get();
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