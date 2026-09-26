<?php

namespace App\Repositories;

use App\Models\CategoriaFinanceira;
use Illuminate\Http\Request;

class CategoriaFinanceiraRepository
{
    public function index(Request $request)
    {
        $busca = $request->get('busca');

        return CategoriaFinanceira::daEmpresa()
            ->when($busca, function ($query) use ($busca) {
                $query->where('nome', 'like', '%' . $busca . '%');
            })
            ->orderBy('tipo')
            ->orderBy('nome')
            ->paginate(20)
            ->withQueryString();
    }

    public function listarAtivasPorTipo(string $tipo)
    {
        return CategoriaFinanceira::daEmpresa()
            ->where('tipo', $tipo)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    public function store(array $data)
    {
        $data['empresa_id'] = auth()->user()->empresa_id;

        return CategoriaFinanceira::create($data);
    }

    public function update(CategoriaFinanceira $categoriaFinanceira, array $data)
    {
        $categoriaFinanceira->update($data);

        return $categoriaFinanceira;
    }

    public function destroy($id)
    {
        $categoria = CategoriaFinanceira::daEmpresa()->findOrFail($id);
        $categoria->delete();

        return $categoria;
    }
}
