<?php

namespace App\Repositories;

use App\Models\ContaReceber;
use App\Models\FormaPagamento;
use App\Models\Venda;
use Illuminate\Http\Request;

class ContaReceberRepository
{
    public function index(?Request $request = null)
    {
        $query = ContaReceber::with(['cliente', 'venda'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderByDesc('id');

        if ($request) {
            if ($request->filled('situacao')) {
                $query->where('situacao', $request->situacao);
            }

            if ($request->filled('data_vencimento')) {
                $query->whereDate('data_vencimento', $request->data_vencimento);
            }
        }

        return $query->get();
    }

    public function findOrFail(int $id): ContaReceber
    {
        return ContaReceber::with(['cliente', 'venda', 'empresa'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);
    }

    public function store(array $dados): ContaReceber
    {
        return ContaReceber::create($dados);
    }

    public function update(ContaReceber $conta, array $dados): ContaReceber
    {
        $conta->update($dados);

        return $conta;
    }

    public function receber(ContaReceber $conta, array $dados): ContaReceber
    {
        $conta->update($dados);

        return $conta;
    }

    public function cancelar(ContaReceber $conta): ContaReceber
    {
        $conta->update(['situacao' => 'cancelada']);

        return $conta;
    }

    public function destroy(int $id): bool
    {
        $conta = ContaReceber::where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);

        return (bool) $conta->delete();
    }

    public function existePorVenda(int $vendaId): bool
    {
        return ContaReceber::where('venda_id', $vendaId)->exists();
    }

    /**
     * Gera Conta a Receber apenas para formas a prazo (duplicata, boleto, etc.).
     * Retorna null se a forma for à vista ou não estiver configurada para gerar.
     */
    public function gerarDaVendaConfirmada(Venda $venda): ?ContaReceber
    {
        if ($this->existePorVenda($venda->id)) {
            return ContaReceber::where('venda_id', $venda->id)->first();
        }

        $forma = FormaPagamento::buscarPorNome($venda->forma_pagamento, (int) $venda->empresa_id);

        if (!$forma || !$forma->isPrazo()) {
            return null;
        }

        $dataVenda = $venda->data_venda;
        $dias = max(0, (int) $forma->dias_vencimento);
        $vencimento = $dataVenda ? $dataVenda->copy()->addDays($dias) : now()->addDays($dias);

        return ContaReceber::create([
            'empresa_id'      => $venda->empresa_id,
            'cliente_id'      => $venda->cliente_id,
            'venda_id'        => $venda->id,
            'descricao'       => 'Venda #' . $venda->numero . ' — ' . $forma->nome,
            'valor'           => $venda->total,
            'valor_pago'      => 0,
            'data_vencimento' => $vencimento->format('Y-m-d'),
            'forma_pagamento' => $forma->nome,
            'situacao'        => 'aberta',
            'observacoes'     => 'Gerada automaticamente na confirmação da venda.',
        ]);
    }
}
