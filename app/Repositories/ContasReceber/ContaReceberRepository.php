<?php

namespace App\Repositories\ContasReceber;

use App\Models\ContasReceber\ContaReceber;
use App\Models\ContasReceber\ContaReceberBaixa;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Vendas\Venda;
use App\Services\CategoriasFinanceiras\CategoriaPadraoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContaReceberRepository
{
    public function index(?Request $request = null)
    {
        $query = ContaReceber::with(['cliente', 'venda'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderByDesc('id');

        if ($request) {
            if ($request->filled('busca')) {
                $busca = $request->busca;
                $query->where(function ($q) use ($busca) {
                    $q->where('descricao', 'like', "%{$busca}%")
                        ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$busca}%"));
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
        return DB::transaction(function () use ($conta, $dados) {
            $conta = ContaReceber::where('id', $conta->id)->lockForUpdate()->firstOrFail();

            if (in_array($conta->situacao, ['paga', 'cancelada'], true)) {
                throw new \RuntimeException('Conta paga ou cancelada não pode ser editada.');
            }

            $pago = round((float) $conta->valor_pago, 2);
            if ($pago > 0 && isset($dados['valor'])) {
                $valor = round((float) $dados['valor'], 2);
                if ($valor < $pago) {
                    throw new \RuntimeException('O valor da conta não pode ser menor que o valor já recebido.');
                }
                $dados['situacao'] = $valor <= $pago ? 'paga' : 'parcial';
            }

            $conta->update($dados);

            return $conta;
        });
    }

    public function receber(ContaReceber $conta, float $valorBaixa, string $dataPagamento, ?string $formaPagamento): ContaReceber
    {
        return DB::transaction(function () use ($conta, $valorBaixa, $dataPagamento, $formaPagamento) {
            $conta = ContaReceber::where('id', $conta->id)->lockForUpdate()->firstOrFail();

            if (in_array($conta->situacao, ['paga', 'cancelada'], true)) {
                throw new \RuntimeException('Não é possível receber em conta paga ou cancelada.');
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

            ContaReceberBaixa::create([
                'empresa_id' => $conta->empresa_id,
                'conta_receber_id' => $conta->id,
                'valor' => $valorBaixa,
                'data_pagamento' => $dataPagamento,
                'forma_pagamento' => $formaPagamento ?: $conta->forma_pagamento,
            ]);

            return $conta;
        });
    }

    public function cancelar(ContaReceber $conta): ContaReceber
    {
        return DB::transaction(function () use ($conta) {
            $conta = ContaReceber::where('id', $conta->id)->lockForUpdate()->firstOrFail();

            if ($conta->situacao !== 'aberta' || (float) $conta->valor_pago > 0) {
                throw new \RuntimeException('Somente conta em aberto e sem recebimento pode ser cancelada.');
            }

            $conta->update(['situacao' => 'cancelada']);

            return $conta;
        });
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
     * Retorna null se a forma for à vista ou se a venda não tiver forma informada.
     * Forma informada mas desconhecida impede a confirmação: sem isso a venda a prazo ficaria sem conta a receber.
     */
    public function gerarDaVendaConfirmada(Venda $venda): ?ContaReceber
    {
        if ($this->existePorVenda($venda->id)) {
            return ContaReceber::where('venda_id', $venda->id)->first();
        }

        $forma = $venda->forma_pagamento_id
            ? FormaPagamento::withTrashed()->where('empresa_id', $venda->empresa_id)->find($venda->forma_pagamento_id)
            : FormaPagamento::buscarPorNome($venda->forma_pagamento, (int) $venda->empresa_id);

        if (!$forma && trim((string) $venda->forma_pagamento) !== '') {
            throw new \RuntimeException(
                'Forma de pagamento "' . $venda->forma_pagamento . '" não encontrada ou inativa. Ajuste a forma de pagamento antes de confirmar.'
            );
        }

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
            'categoria_id'    => app(CategoriaPadraoService::class)->idReceitaVendas((int) $venda->empresa_id),
            'situacao'        => 'aberta',
            'observacoes'     => 'Gerada automaticamente na confirmação da venda.',
        ]);
    }
}
