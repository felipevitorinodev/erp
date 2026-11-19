<?php

namespace App\Repositories\Estoque;

use App\Models\Empresas\Empresa;
use App\Models\Estoque\EntradaEstoque;
use App\Models\Estoque\PedidoCompra;
use App\Models\Estoque\PedidoCompraItem;
use App\Models\Fornecedores\Fornecedor;
use App\Models\Produtos\Produto;
use App\Support\BuscaPorNome;
use App\Support\ContagemPaginada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedidoCompraRepository
{
    public function index(Request $request)
    {
        $query = PedidoCompra::with(['fornecedor:id,nome'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderByDesc('id');

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('numero', 'like', "%{$busca}%")
                    ->orWhereIn('fornecedor_id', BuscaPorNome::ids(Fornecedor::class, (int) auth()->user()->empresa_id, $busca));
            });
        }

        if ($request->filled('situacao')) {
            $query->where('situacao', $request->situacao);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDataDe('data_emissao', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDataAte('data_emissao', $request->data_fim);
        }

        return $query->paginate(20, ['*'], 'page', null, ContagemPaginada::total($query, ['estoque', 'cadastros']));
    }

    public function findOrFail(int $id): PedidoCompra
    {
        return PedidoCompra::with(['fornecedor', 'itens.produto', 'entradasEstoque'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);
    }

    public function store(array $dados, array $itens): PedidoCompra
    {
        return DB::transaction(function () use ($dados, $itens) {
            $numeroSeq = $this->proximoNumeroSequencial((int) $dados['empresa_id']);
            $pedido = PedidoCompra::create(array_merge($dados, [
                'numero_seq' => $numeroSeq,
                'numero' => 'PC-'.str_pad((string) $numeroSeq, 6, '0', STR_PAD_LEFT),
                'situacao' => 'rascunho',
            ]));

            $this->salvarItens($pedido, $itens);

            return $pedido;
        });
    }

    public function update(PedidoCompra $pedido, array $dados, array $itens): PedidoCompra
    {
        return DB::transaction(function () use ($pedido, $dados, $itens) {
            $pedido = PedidoCompra::where('empresa_id', auth()->user()->empresa_id)
                ->whereKey($pedido->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($pedido->situacao !== 'rascunho') {
                throw new \RuntimeException('Somente pedidos em rascunho podem ser editados.');
            }

            $pedido->update($dados);
            $pedido->itens()->delete();
            $this->salvarItens($pedido, $itens);

            return $pedido;
        });
    }

    public function aprovar(PedidoCompra $pedido): PedidoCompra
    {
        return DB::transaction(function () use ($pedido) {
            $pedido = PedidoCompra::whereKey($pedido->id)->lockForUpdate()->firstOrFail();

            if ($pedido->situacao !== 'rascunho' || ! $pedido->itens()->exists()) {
                throw new \RuntimeException('Somente pedidos em rascunho com itens podem ser aprovados.');
            }

            $pedido->update(['situacao' => 'aprovado']);

            return $pedido;
        });
    }

    public function cancelar(PedidoCompra $pedido): PedidoCompra
    {
        return DB::transaction(function () use ($pedido) {
            $pedido = PedidoCompra::whereKey($pedido->id)->lockForUpdate()->firstOrFail();

            if (! in_array($pedido->situacao, ['rascunho', 'aprovado', 'parcial'], true)) {
                throw new \RuntimeException('Somente pedidos em aberto podem ser cancelados.');
            }

            $entradaEmRascunho = EntradaEstoque::where('pedido_compra_id', $pedido->id)
                ->where('situacao', 'rascunho')
                ->exists();
            if ($entradaEmRascunho) {
                throw new \RuntimeException('Cancele ou exclua primeiro a entrada em rascunho vinculada a este pedido.');
            }

            $pedido->update(['situacao' => 'cancelado']);

            return $pedido;
        });
    }

    public function destroy(PedidoCompra $pedido): bool
    {
        return DB::transaction(function () use ($pedido) {
            $pedido = PedidoCompra::where('empresa_id', auth()->user()->empresa_id)
                ->whereKey($pedido->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($pedido->situacao, ['rascunho', 'cancelado'], true)) {
                throw new \RuntimeException('Cancele o pedido antes de excluí-lo.');
            }

            if ($pedido->entradasEstoque()->exists()) {
                throw new \RuntimeException('Pedido com entradas vinculadas não pode ser excluído.');
            }

            return (bool) $pedido->delete();
        });
    }

    public function validarRecebimento(EntradaEstoque $entrada): ?PedidoCompra
    {
        if (! $entrada->pedido_compra_id) {
            return null;
        }

        $pedido = PedidoCompra::where('empresa_id', $entrada->empresa_id)
            ->whereKey($entrada->pedido_compra_id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! in_array($pedido->situacao, ['aprovado', 'parcial'], true)) {
            throw new \RuntimeException('Somente pedidos aprovados ou parcialmente recebidos podem receber itens.');
        }

        if ((int) $pedido->fornecedor_id !== (int) $entrada->fornecedor_id) {
            throw new \RuntimeException('O fornecedor da entrada deve ser o mesmo do pedido de compra.');
        }

        $itensPedido = PedidoCompraItem::where('pedido_compra_id', $pedido->id)
            ->lockForUpdate()
            ->get()
            ->keyBy('produto_id');
        $quantidades = [];

        foreach ($entrada->itens as $item) {
            if (! $item->produto_id) {
                throw new \RuntimeException('Vincule cada item da entrada a um produto do pedido de compra.');
            }

            $quantidades[$item->produto_id] = ($quantidades[$item->produto_id] ?? 0) + (float) $item->quantidade;
        }

        foreach ($quantidades as $produtoId => $quantidade) {
            $itemPedido = $itensPedido->get($produtoId);
            if (! $itemPedido) {
                throw new \RuntimeException('A entrada contém produto que não pertence ao pedido de compra.');
            }

            $saldo = (float) $itemPedido->quantidade - (float) $itemPedido->quantidade_recebida;
            if ($quantidade > $saldo + 0.0005) {
                throw new \RuntimeException(
                    'A quantidade recebida do produto "'.$itemPedido->produto_nome.'" excede o saldo pendente de '
                    .number_format(max(0, $saldo), 3, ',', '.').'.'
                );
            }
        }

        return $pedido;
    }

    public function validarEstorno(EntradaEstoque $entrada): ?PedidoCompra
    {
        if (! $entrada->pedido_compra_id) {
            return null;
        }

        $pedido = PedidoCompra::where('empresa_id', $entrada->empresa_id)
            ->whereKey($entrada->pedido_compra_id)
            ->lockForUpdate()
            ->firstOrFail();
        $itensPedido = PedidoCompraItem::where('pedido_compra_id', $pedido->id)
            ->lockForUpdate()
            ->get()
            ->keyBy('produto_id');
        $quantidades = [];

        foreach ($entrada->itens as $item) {
            if (! $item->produto_id) {
                throw new \RuntimeException('Não foi possível localizar o produto recebido no pedido de compra.');
            }

            $quantidades[$item->produto_id] = ($quantidades[$item->produto_id] ?? 0) + (float) $item->quantidade;
        }

        foreach ($quantidades as $produtoId => $quantidade) {
            $itemPedido = $itensPedido->get($produtoId);
            if (! $itemPedido || (float) $itemPedido->quantidade_recebida + 0.0005 < $quantidade) {
                throw new \RuntimeException('Não foi possível localizar o saldo recebido deste item no pedido de compra.');
            }
        }

        return $pedido;
    }

    public function aplicarRecebimento(PedidoCompra $pedido, iterable $itens, int $sinal): void
    {
        $itensPedido = PedidoCompraItem::where('pedido_compra_id', $pedido->id)
            ->lockForUpdate()
            ->get()
            ->keyBy('produto_id');
        $quantidades = [];

        foreach ($itens as $item) {
            $quantidades[$item->produto_id] = ($quantidades[$item->produto_id] ?? 0) + (float) $item->quantidade;
        }

        foreach ($quantidades as $produtoId => $quantidade) {
            $itemPedido = $itensPedido->get($produtoId);
            if (! $itemPedido) {
                throw new \RuntimeException('Item da entrada não pertence ao pedido de compra vinculado.');
            }

            $recebido = round((float) $itemPedido->quantidade_recebida + ($sinal * $quantidade), 3);
            if ($recebido < -0.0005 || $recebido > (float) $itemPedido->quantidade + 0.0005) {
                throw new \RuntimeException('A quantidade recebida do pedido de compra ficou inconsistente.');
            }

            $itemPedido->update(['quantidade_recebida' => max(0, $recebido)]);
        }

        if ($pedido->situacao !== 'cancelado') {
            $pedido->load('itens');
            $total = (float) $pedido->itens->sum('quantidade');
            $recebido = (float) $pedido->itens->sum('quantidade_recebida');
            $situacao = $recebido <= 0.0005
                ? 'aprovado'
                : ($recebido + 0.0005 >= $total ? 'recebido' : 'parcial');

            $pedido->update(['situacao' => $situacao]);
        }
    }

    private function proximoNumeroSequencial(int $empresaId): int
    {
        Empresa::whereKey($empresaId)->lockForUpdate()->firstOrFail(['id']);

        return ((int) PedidoCompra::withTrashed()
            ->where('empresa_id', $empresaId)
            ->max('numero_seq')) + 1;
    }

    private function salvarItens(PedidoCompra $pedido, array $itens): void
    {
        $produtos = Produto::where('empresa_id', $pedido->empresa_id)
            ->whereIn('id', array_column($itens, 'produto_id'))
            ->get()
            ->keyBy('id');

        foreach ($itens as $item) {
            $produto = $produtos->get($item['produto_id']);
            if (! $produto) {
                throw new \RuntimeException('Produto inválido para esta empresa.');
            }

            PedidoCompraItem::create([
                'pedido_compra_id' => $pedido->id,
                'produto_id' => $produto->id,
                'produto_nome' => $produto->nome,
                'produto_codigo' => $produto->codigo,
                'quantidade' => $item['quantidade'],
                'quantidade_recebida' => 0,
                'preco_unitario' => $item['preco_unitario'],
                'desconto' => $item['desconto'] ?? 0,
                'total' => round(((float) $item['quantidade'] * (float) $item['preco_unitario']) - (float) ($item['desconto'] ?? 0), 2),
            ]);
        }
    }
}
