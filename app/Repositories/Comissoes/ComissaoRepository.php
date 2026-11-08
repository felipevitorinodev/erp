<?php

namespace App\Repositories\Comissoes;

use App\Models\Comissoes\Comissao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ComissaoRepository
{
    public function index(Request $request)
    {
        $query = $this->filtrar($request)
            ->with([
                'funcionario:id,nome',
                'venda:id,numero,data_venda',
                'venda.ordemServico:id,venda_id,numero',
            ])
            ->orderByDesc('id');

        return $query->paginate(20);
    }

    public function totais(Request $request): array
    {
        $somas = $this->filtrar($request)
            ->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'pendente' THEN valor END), 0) as pendentes")
            ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'paga' THEN valor END), 0) as pagas")
            ->first();

        return [
            'pendentes' => (float) $somas->pendentes,
            'pagas' => (float) $somas->pagas,
        ];
    }

    public function marcarPaga(Comissao $comissao): Comissao
    {
        $comissao->update([
            'situacao' => 'paga',
            'data_pagamento' => now()->toDateString(),
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

        if ($request->filled('data_inicio') || $request->filled('data_fim')) {
            $query->whereHas('venda', function ($q) use ($request) {
                if ($request->filled('data_inicio')) {
                    $q->whereDataDe('data_venda', $request->data_inicio);
                }
                if ($request->filled('data_fim')) {
                    $q->whereDataAte('data_venda', $request->data_fim);
                }
            });
        }

        return $query;
    }
}
