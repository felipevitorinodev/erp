<?php

namespace App\Repositories;

use App\Models\FormaPagamento;
use Illuminate\Http\Request;

class FormaPagamentoRepository
{
    public function index(?Request $request = null)
    {
        $query = FormaPagamento::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome');

        if ($request && $request->filled('busca')) {
            $busca = $request->busca;
            $query->where('nome', 'like', "%{$busca}%");
        }

        return $query->paginate(20);
    }

    public function store(array $data)
    {
        return FormaPagamento::create($data);
    }

    public function update(FormaPagamento $formaPagamento, array $data)
    {
        $formaPagamento->update($data);

        return $formaPagamento;
    }

    public function destroy($id)
    {
        $formaPagamento = FormaPagamento::where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);

        $formaPagamento->delete();

        return $formaPagamento;
    }
}
