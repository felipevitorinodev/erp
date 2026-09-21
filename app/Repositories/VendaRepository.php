<?php

namespace App\Repositories;

use App\Models\ContaReceber;
use App\Models\Produto;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Support\Facades\DB;

class VendaRepository
{
    public function __construct(
        protected ContaReceberRepository $contaReceberRepository
    ) {}

    public function index()
    {
        return Venda::with(['cliente'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderByDesc('id')
            ->get();
    }

    public function findOrFail(int $id): Venda
    {
        return Venda::with(['cliente', 'itens.produto', 'usuario'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);
    }

    public function proximoNumero(int $empresaId): string
    {
        $ultimo = Venda::where('empresa_id', $empresaId)
            ->max('numero');

        $proximo = $ultimo ? ((int) $ultimo) + 1 : 1;

        return str_pad($proximo, 6, '0', STR_PAD_LEFT);
    }

    public function store(array $dados, array $itens): Venda
    {
        return DB::transaction(function () use ($dados, $itens) {
            $venda = Venda::create($dados);
            $this->salvarItens($venda, $itens);
            return $venda;
        });
    }

    public function update(Venda $venda, array $dados, array $itens): Venda
    {
        return DB::transaction(function () use ($venda, $dados, $itens) {
            if ($venda->situacao === 'confirmada') {
                $this->estornarEstoque($venda);
            }

            $venda->update($dados);

            $venda->itens()->delete();
            $this->salvarItens($venda, $itens);

            if ($venda->situacao === 'confirmada') {
                $this->aplicarEstoque($venda->fresh('itens'));
            }

            return $venda;
        });
    }

    public function confirmar(Venda $venda): Venda
    {
        return DB::transaction(function () use ($venda) {
            $venda->update(['situacao' => 'confirmada']);
            $this->aplicarEstoque($venda->load('itens'));

            // Vendas a prazo (duplicata, boleto, etc.) geram Conta a Receber
            $this->contaReceberRepository->gerarDaVendaConfirmada($venda);

            return $venda;
        });
    }

    public function cancelar(Venda $venda, string $situacao = 'cancelada'): Venda
    {
        return DB::transaction(function () use ($venda, $situacao) {
            if ($venda->situacao === 'confirmada') {
                $this->estornarEstoque($venda);

                ContaReceber::where('venda_id', $venda->id)
                    ->whereIn('situacao', ['aberta', 'parcial'])
                    ->update(['situacao' => 'cancelada']);
            }

            $venda->update(['situacao' => $situacao]);

            return $venda;
        });
    }

    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $venda = Venda::where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);

            if ($venda->situacao === 'confirmada') {
                $this->estornarEstoque($venda);
            }

            return (bool) $venda->delete();
        });
    }

    private function salvarItens(Venda $venda, array $itens): void
    {
        foreach ($itens as $item) {
            VendaItem::create(array_merge($item, ['venda_id' => $venda->id]));
        }
    }

    private function aplicarEstoque(Venda $venda): void
    {
        // Primeiro validamos se há estoque suficiente para todos os itens que controlam estoque.
        $insuficientes = [];
        foreach ($venda->itens as $item) {
            if (!$item->produto_id) continue;
            $produto = Produto::where('id', $item->produto_id)
                ->where('controla_estoque', true)
                ->first();

            if ($produto) {
                // compara estoque atual com a quantidade a decrementar
                if ($produto->estoque_atual < $item->quantidade) {
                    $insuficientes[] = [
                        'produto_nome' => $produto->nome,
                        'disponivel' => $produto->estoque_atual,
                        'requerido' => $item->quantidade
                    ];
                }
            }
        }

        if (!empty($insuficientes)) {
            // monta mensagem simples com o primeiro produto insuficiente
            $p = $insuficientes[0];
            throw new \Exception("Estoque insuficiente para o produto \"{$p['produto_nome']}\". Disponível: {$p['disponivel']}, necessário: {$p['requerido']}.");
        }

        // Se passou na validação, aplica as decrementações
        foreach ($venda->itens as $item) {
            if ($item->produto_id) {
                Produto::where('id', $item->produto_id)
                    ->where('empresa_id', $venda->empresa_id)
                    ->where('controla_estoque', true)
                    ->decrement('estoque_atual', $item->quantidade);
            }
        }
    }

    private function estornarEstoque(Venda $venda): void
    {
        foreach ($venda->itens as $item) {
            if ($item->produto_id) {
                Produto::where('id', $item->produto_id)
                    ->where('empresa_id', $venda->empresa_id)
                    ->where('controla_estoque', true)
                    ->increment('estoque_atual', $item->quantidade);
            }
        }
    }
}
