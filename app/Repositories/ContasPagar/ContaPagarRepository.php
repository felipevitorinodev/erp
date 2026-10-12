<?php

namespace App\Repositories\ContasPagar;

use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasPagar\ContaPagarBaixa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function pagar(ContaPagar $conta, float $valorBaixa, string $dataPagamento, ?string $formaPagamento): ContaPagar
    {
        return DB::transaction(function () use ($conta, $valorBaixa, $dataPagamento, $formaPagamento) {
            $conta = ContaPagar::where('id', $conta->id)->lockForUpdate()->firstOrFail();

            $saldo = round((float) $conta->valor - (float) $conta->valor_pago, 2);
            if ($valorBaixa <= 0 || $valorBaixa > $saldo) {
                throw new \RuntimeException('O valor pago não pode exceder o saldo restante.');
            }

            $novoValorPago = round((float) $conta->valor_pago + $valorBaixa, 2);
            $situacao = $novoValorPago >= round((float) $conta->valor, 2) ? 'paga' : 'parcial';

            $conta->update([
                'valor_pago' => $novoValorPago,
                'situacao' => $situacao,
                'forma_pagamento' => $formaPagamento ?: $conta->forma_pagamento,
                'data_pagamento' => $dataPagamento,
            ]);

            ContaPagarBaixa::create([
                'empresa_id' => $conta->empresa_id,
                'conta_pagar_id' => $conta->id,
                'valor' => $valorBaixa,
                'data_pagamento' => $dataPagamento,
                'forma_pagamento' => $formaPagamento ?: $conta->forma_pagamento,
            ]);

            return $conta;
        });
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
