<?php

namespace App\Services\Estoque;

use App\Http\Requests\Estoque\PedidoCompraRequest;
use App\Models\Estoque\PedidoCompra;
use App\Repositories\Estoque\PedidoCompraRepository;
use App\Support\Auditoria;
use App\Support\MensagemPublica;
use Illuminate\Http\Request;
use RuntimeException;

class PedidoCompraService
{
    public function __construct(
        protected PedidoCompraRepository $repository
    ) {}

    public function index(Request $request)
    {
        return view('pedido-compra.index', [
            'pedidos' => $this->repository->index($request),
            'filtros' => $request->only(['busca', 'situacao', 'data_inicio', 'data_fim']),
        ]);
    }

    public function create()
    {
        return view('pedido-compra.create');
    }

    public function store(PedidoCompraRequest $request)
    {
        [$dados, $itens] = $this->dadosDaRequest($request);

        try {
            $pedido = $this->repository->store($dados, $itens);
        } catch (RuntimeException $e) {
            return back()->withErrors(['itens' => MensagemPublica::de($e)])->withInput();
        }

        Auditoria::registrar('pedido_compra.criado', 'pedido_compra', $pedido->id, [
            'numero' => $pedido->numero,
            'total' => (float) $pedido->total,
        ]);

        return redirect()->route('pedido-compra.show', $pedido)
            ->with('success', 'Pedido de compra '.$pedido->numero.' criado como rascunho.');
    }

    public function show(PedidoCompra $pedidoCompra)
    {
        $this->autorizarPedido($pedidoCompra);
        $pedido = $this->repository->findOrFail($pedidoCompra->id);

        return view('pedido-compra.show', ['pedido' => $pedido]);
    }

    public function edit(PedidoCompra $pedidoCompra)
    {
        $this->autorizarPedido($pedidoCompra);

        if ($pedidoCompra->situacao !== 'rascunho') {
            return redirect()->route('pedido-compra.show', $pedidoCompra)
                ->with('error', 'Somente pedidos em rascunho podem ser editados.');
        }

        $pedido = $this->repository->findOrFail($pedidoCompra->id);

        return view('pedido-compra.edit', ['pedido' => $pedido]);
    }

    public function update(PedidoCompraRequest $request, PedidoCompra $pedidoCompra)
    {
        $this->autorizarPedido($pedidoCompra);
        [$dados, $itens] = $this->dadosDaRequest($request);

        try {
            $this->repository->update($pedidoCompra, $dados, $itens);
        } catch (RuntimeException $e) {
            return redirect()->route('pedido-compra.show', $pedidoCompra)
                ->with('error', MensagemPublica::de($e));
        }

        return redirect()->route('pedido-compra.show', $pedidoCompra)
            ->with('success', 'Pedido de compra '.$pedidoCompra->numero.' atualizado.');
    }

    public function aprovar(PedidoCompra $pedidoCompra)
    {
        $this->autorizarPedido($pedidoCompra);

        try {
            $pedido = $this->repository->aprovar($pedidoCompra);
        } catch (RuntimeException $e) {
            return redirect()->route('pedido-compra.show', $pedidoCompra)
                ->with('error', MensagemPublica::de($e));
        }

        Auditoria::registrar('pedido_compra.aprovado', 'pedido_compra', $pedido->id, [
            'numero' => $pedido->numero,
            'total' => (float) $pedido->total,
        ]);

        return redirect()->route('pedido-compra.show', $pedido)
            ->with('success', 'Pedido de compra aprovado. A conta a pagar será criada somente após confirmar a entrada.');
    }

    public function cancelar(PedidoCompra $pedidoCompra)
    {
        $this->autorizarPedido($pedidoCompra);

        try {
            $pedido = $this->repository->cancelar($pedidoCompra);
        } catch (RuntimeException $e) {
            return redirect()->route('pedido-compra.show', $pedidoCompra)
                ->with('error', MensagemPublica::de($e));
        }

        Auditoria::registrar('pedido_compra.cancelado', 'pedido_compra', $pedido->id, [
            'numero' => $pedido->numero,
            'situacao_anterior' => $pedidoCompra->situacao,
        ]);

        return redirect()->route('pedido-compra.show', $pedido)
            ->with('success', 'Pedido cancelado. Os recebimentos já confirmados permanecem registrados.');
    }

    public function destroy(PedidoCompra $pedidoCompra)
    {
        $this->autorizarPedido($pedidoCompra);

        try {
            $this->repository->destroy($pedidoCompra);
        } catch (RuntimeException $e) {
            return redirect()->route('pedido-compra.show', $pedidoCompra)
                ->with('error', MensagemPublica::de($e));
        }

        return redirect()->route('pedido-compra.index')
            ->with('success', 'Pedido de compra '.$pedidoCompra->numero.' excluído.');
    }

    private function dadosDaRequest(PedidoCompraRequest $request): array
    {
        $validados = $request->validated();
        $itens = array_map(function (array $item) {
            return [
                'produto_id' => (int) $item['produto_id'],
                'quantidade' => (float) $item['quantidade'],
                'preco_unitario' => (float) $item['preco_unitario'],
                'desconto' => (float) ($item['desconto'] ?? 0),
            ];
        }, $validados['itens']);

        $subtotal = round(array_sum(array_map(
            fn (array $item) => ((float) $item['quantidade'] * (float) $item['preco_unitario']) - (float) $item['desconto'],
            $itens
        )), 2);
        $desconto = (float) ($validados['desconto'] ?? 0);

        return [[
            'empresa_id' => (int) auth()->user()->empresa_id,
            'fornecedor_id' => (int) $validados['fornecedor_id'],
            'data_emissao' => $validados['data_emissao'],
            'data_prevista' => $validados['data_prevista'] ?? null,
            'subtotal' => $subtotal,
            'desconto' => $desconto,
            'total' => max(0, round($subtotal - $desconto, 2)),
            'observacoes' => $validados['observacoes'] ?? null,
        ], $itens];
    }

    private function autorizarPedido(PedidoCompra $pedido): void
    {
        if ((int) $pedido->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }
}
