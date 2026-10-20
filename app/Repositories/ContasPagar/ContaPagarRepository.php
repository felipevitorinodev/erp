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
        return DB::transaction(function () use ($conta, $dados) {
            $conta = ContaPagar::where('id', $conta->id)->lockForUpdate()->firstOrFail();

            if (in_array($conta->situacao, ['paga', 'cancelada'], true)) {
                throw new \RuntimeException('Conta paga ou cancelada não pode ser editada.');
            }

            $pago = round((float) $conta->valor_pago, 2);
            if ($pago > 0 && isset($dados['valor'])) {
                $valor = round((float) $dados['valor'], 2);
                if ($valor < $pago) {
                    throw new \RuntimeException('O valor da conta não pode ser menor que o valor já pago.');
                }
                $dados['situacao'] = $valor <= $pago ? 'paga' : 'parcial';
            }

            $conta->update($dados);

            return $conta;
        });
    }

    public function pagar(ContaPagar $conta, float $valorBaixa, string $dataPagamento, ?string $formaPagamento): ContaPagar
    {
        return DB::transaction(function () use ($conta, $valorBaixa, $dataPagamento, $formaPagamento) {
            $conta = ContaPagar::where('id', $conta->id)->lockForUpdate()->firstOrFail();

            if (in_array($conta->situacao, ['paga', 'cancelada'], true)) {
                throw new \RuntimeException('Não é possível pagar conta paga ou cancelada.');
            }

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
        return DB::transaction(function () use ($conta) {
            $conta = ContaPagar::where('id', $conta->id)->lockForUpdate()->firstOrFail();

            if ($conta->situacao !== 'aberta' || (float) $conta->valor_pago > 0) {
                throw new \RuntimeException('Somente conta em aberto e sem pagamento pode ser cancelada.');
            }

            $conta->update(['situacao' => 'cancelada']);

            return $conta;
        });
    }

    public function destroy(int $id): bool
    {
        $conta = ContaPagar::where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);

        return (bool) $conta->delete();
    }
}
