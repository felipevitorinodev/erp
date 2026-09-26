<?php

namespace App\Repositories;

use App\Models\ContaPagar;
use App\Models\EntradaEstoque;
use App\Models\EntradaEstoqueItem;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Services\CategoriaPadraoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntradaEstoqueRepository
{
    public function index(Request $request)
    {
        $query = EntradaEstoque::with(['fornecedor'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderByDesc('id');

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('numero', 'like', "%{$busca}%")
                    ->orWhereHas('fornecedor', fn ($f) => $f->where('nome', 'like', "%{$busca}%"));
            });
        }

        if ($request->filled('situacao')) {
            $query->where('situacao', $request->situacao);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('data_entrada', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('data_entrada', '<=', $request->data_fim);
        }

        return $query->paginate(20);
    }

    public function store(array $dados, array $itens): EntradaEstoque
    {
        return DB::transaction(function () use ($dados, $itens) {
            $entrada = EntradaEstoque::create($dados);
            $this->salvarItens($entrada, $itens);

            return $entrada;
        });
    }

    public function update(EntradaEstoque $entrada, array $dados, array $itens): EntradaEstoque
    {
        return DB::transaction(function () use ($entrada, $dados, $itens) {
            $entrada->update($dados);
            $entrada->itens()->delete();
            $this->salvarItens($entrada, $itens);

            return $entrada;
        });
    }

    public function confirmar(EntradaEstoque $entrada): EntradaEstoque
    {
        return DB::transaction(function () use ($entrada) {
            $entrada = EntradaEstoque::where('id', $entrada->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($entrada->situacao !== 'rascunho') {
                throw new \Exception('Somente entradas em rascunho podem ser confirmadas.');
            }

            $entrada->load(['itens.produto', 'fornecedor']);

            foreach ($entrada->itens as $item) {
                if (!$item->produto_id) {
                    continue;
                }

                $produto = Produto::where('id', $item->produto_id)
                    ->where('empresa_id', $entrada->empresa_id)
                    ->where('controla_estoque', true)
                    ->lockForUpdate()
                    ->first();

                if (!$produto) {
                    continue;
                }

                $antes = (float) $produto->estoque_atual;
                $depois = $antes + (float) $item->quantidade;

                $produto->increment('estoque_atual', $item->quantidade);
                $produto->update(['preco_custo' => $item->preco_unitario]);

                MovimentacaoEstoque::create([
                    'empresa_id'     => $entrada->empresa_id,
                    'produto_id'     => $produto->id,
                    'user_id'        => auth()->id(),
                    'tipo'           => 'entrada',
                    'quantidade'     => $item->quantidade,
                    'estoque_antes'  => $antes,
                    'estoque_depois' => $depois,
                    'motivo'         => 'Entrada #' . $entrada->numero,
                    'origem'         => 'entrada_estoque',
                    'origem_id'      => $entrada->id,
                ]);
            }

            $jaTemConta = ContaPagar::where('entrada_estoque_id', $entrada->id)
                ->whereNotIn('situacao', ['cancelada'])
                ->exists();

            if (!$jaTemConta) {
                ContaPagar::create([
                    'empresa_id'         => $entrada->empresa_id,
                    'fornecedor_id'      => $entrada->fornecedor_id,
                    'entrada_estoque_id' => $entrada->id,
                    'descricao'          => 'Entrada #' . $entrada->numero . ' — ' . ($entrada->fornecedor->nome ?? 'Fornecedor não informado'),
                    'valor'              => $entrada->total,
                    'valor_pago'         => 0,
                    'data_vencimento'    => $entrada->data_entrada
                        ? $entrada->data_entrada->copy()->addDays(30)
                        : now()->addDays(30),
                    'categoria_id'       => app(CategoriaPadraoService::class)->idDespesaCompras((int) $entrada->empresa_id),
                    'situacao'           => 'aberta',
                ]);
            }

            $entrada->update(['situacao' => 'confirmada']);

            return $entrada;
        });
    }

    public function cancelar(EntradaEstoque $entrada): EntradaEstoque
    {
        return DB::transaction(function () use ($entrada) {
            $temPago = ContaPagar::where('entrada_estoque_id', $entrada->id)
                ->whereIn('situacao', ['paga', 'parcial'])
                ->exists();

            if ($temPago) {
                throw new \Exception('Não é possível cancelar: a conta a pagar vinculada já foi paga ou parcialmente paga.');
            }

            $entrada->load('itens.produto');

            foreach ($entrada->itens as $item) {
                if (!$item->produto_id) {
                    continue;
                }

                $produto = Produto::where('id', $item->produto_id)
                    ->where('empresa_id', $entrada->empresa_id)
                    ->where('controla_estoque', true)
                    ->lockForUpdate()
                    ->first();

                if (!$produto) {
                    continue;
                }

                $antes = (float) $produto->estoque_atual;

                if ($antes < (float) $item->quantidade) {
                    throw new \Exception(
                        'Não é possível cancelar: estoque insuficiente para estornar o produto "'
                        . ($produto->nome ?? $item->produto_nome)
                        . '". Disponível: ' . number_format($antes, 2, ',', '.')
                        . ', necessário: ' . number_format((float) $item->quantidade, 2, ',', '.') . '.'
                    );
                }

                $depois = $antes - (float) $item->quantidade;

                $produto->decrement('estoque_atual', $item->quantidade);

                MovimentacaoEstoque::create([
                    'empresa_id'     => $entrada->empresa_id,
                    'produto_id'     => $produto->id,
                    'user_id'        => auth()->id(),
                    'tipo'           => 'saida',
                    'quantidade'     => $item->quantidade,
                    'estoque_antes'  => $antes,
                    'estoque_depois' => $depois,
                    'motivo'         => 'Estorno entrada #' . $entrada->numero,
                    'origem'         => 'entrada_estoque',
                    'origem_id'      => $entrada->id,
                ]);
            }

            ContaPagar::where('entrada_estoque_id', $entrada->id)
                ->whereIn('situacao', ['aberta', 'parcial'])
                ->update(['situacao' => 'cancelada']);

            $entrada->update(['situacao' => 'cancelada']);

            return $entrada;
        });
    }

    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $entrada = EntradaEstoque::where('empresa_id', auth()->user()->empresa_id)
                ->findOrFail($id);

            if ($entrada->situacao !== 'rascunho') {
                throw new \Exception('Somente entradas em rascunho podem ser excluídas.');
            }

            return (bool) $entrada->delete();
        });
    }

    private function salvarItens(EntradaEstoque $entrada, array $itens): void
    {
        foreach ($itens as $item) {
            EntradaEstoqueItem::create(array_merge($item, [
                'entrada_estoque_id' => $entrada->id,
            ]));
        }
    }
}
