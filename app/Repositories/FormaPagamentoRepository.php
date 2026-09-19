<?php

namespace App\Repositories;

use App\Models\FormaPagamento;

class FormaPagamentoRepository
{
    public function index()
    {
        return FormaPagamento::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome')
            ->get();
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
