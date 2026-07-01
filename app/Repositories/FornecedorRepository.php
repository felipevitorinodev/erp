<?php

namespace App\Repositories;

use App\Models\Fornecedor;

class FornecedorRepository
{
    public function index()
    {
        return Fornecedor::orderBy('nome')->get();
    }

    public function store(array $data)
    {
        return Fornecedor::create($data);
    }

    public function update(Fornecedor $fornecedor, array $data)
    {
        $fornecedor->update($data);

        return $fornecedor;
    }

    public function destroy($id)
    {
        $fornecedor = Fornecedor::findOrFail($id);
        $fornecedor->delete();

        return $fornecedor;
    }
}