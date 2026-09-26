<?php

namespace App\Repositories;

use App\Models\Comissao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ComissaoRepository
{
    public function index(Request $request)
    {
        $query = $this->filtrar($request)
            ->with(['funcionario', 'venda.cliente'])
            ->orderByDesc('id');

        return $query->paginate(20);
    }

    public function totais(Request $request): array
    {
        $base = $this->filtrar($request);

        return [
            'pendentes' => (clone $base)->where('situacao', 'pendente')->sum('valor'),
            'pagas'     => (clone $base)->where('situacao', 'paga')->sum('valor'),
        ];
    }

    public function marcarPaga(Comissao $comissao): Comissao
    {
        $comissao->update([
            'situacao'        => 'paga',
            'data_pagamento'  => now()->toDateString(),
        ]);

        return $comissao->fresh();
    }

    protected function filtrar(Request $request): Builder
    {
        $query = Comissao::where('empresa_id', auth()->user()->empresa_id);

        if ($request->filled('funcionario_id')) {
            $query->where('funcionario_id', $request->funcionario_id);
        }

        if ($request->filled('situacao')) {
            $query->where('situacao', $request->situacao);
        }

        if ($request->filled('data_inicio')) {
            $query->whereHas('venda', fn ($q) => $q->whereDate('data_venda', '>=', $request->data_inicio));
        }

        if ($request->filled('data_fim')) {
            $query->whereHas('venda', fn ($q) => $q->whereDate('data_venda', '<=', $request->data_fim));
        }

        return $query;
    }
}
