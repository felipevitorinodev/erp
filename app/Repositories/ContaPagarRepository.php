<?php

namespace App\Repositories;

use App\Models\ContaPagar;
use Illuminate\Http\Request;

class ContaPagarRepository
{
    public function index(?Request $request = null)
    {
        $query = ContaPagar::with(['fornecedor'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderByDesc('id');

        if ($request) {
            if ($request->filled('busca')) {
                $busca = $request->busca;
                $query->where(function ($q) use ($busca) {
                    $q->where('descricao', 'like', "%{$busca}%")
                        ->orWhereHas('fornecedor', fn ($c) => $c->where('nome', 'like', "%{$busca}%"));
                });
            }
            if ($request->filled('situacao')) {
                $query->where('situacao', $request->situacao);
            }

            if ($request->filled('data_vencimento')) {
                $query->whereDate('data_vencimento', $request->data_vencimento);
            }
        }

        return $query->paginate(20);
    }

    public function findOrFail(int $id): ContaPagar
    {
        return ContaPagar::with(['fornecedor', 'empresa'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);
    }

    public function store(array $dados): ContaPagar
    {
        return ContaPagar::create($dados);
    }

    public function update(ContaPagar $conta, array $dados): ContaPagar
    {
        $conta->update($dados);

        return $conta;
    }

    public function pagar(ContaPagar $conta, array $dados): ContaPagar
    {
        $conta->update($dados);

        return $conta;
    }

    public function cancelar(ContaPagar $conta): ContaPagar
    {
        $conta->update(['situacao' => 'cancelada']);

        return $conta;
    }

    public function destroy(int $id): bool
    {
        $conta = ContaPagar::where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);

        return (bool) $conta->delete();
    }
}
