<?php

namespace App\Repositories;

use App\Models\Produto;

class ProdutoRepository
{
    public function index()
    {
        return Produto::with(['grupos', 'unidadeMedida'])
            ->orderBy('nome')
            ->get();
    }

    public function store(array $data)
    {
        return Produto::create($data);
    }

    public function update(Produto $produto, array $data)
    {
        $produto->update($data);

        return $produto;
    }

    public function destroy($id)
    {
        $produto = Produto::findOrFail($id);
        $produto->delete();

        return $produto;
    }
}