<?php

namespace App\Repositories;

use App\Models\Produto;

class ProdutoRepository
{
    public function index()
    {
        return Produto::with(['grupos', 'unidadeMedida'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome')
            ->get();
    }

    public function store(array $data)
    {
        $data['empresa_id'] = auth()->user()->empresa_id;
        return Produto::create($data);
    }

    public function update(Produto $produto, array $data)
    {
        $produto->update($data);

        return $produto;
    }

    public function destroy($id)
    {
        $produto = Produto::where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);
        $produto->delete();

        return $produto;
    }
}