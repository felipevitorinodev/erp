<?php

namespace App\Services\Orcamentos;

use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Orcamentos\Orcamento;
use App\Models\Produtos\Produto;
use App\Repositories\Orcamentos\OrcamentoRepository;
use App\Repositories\Vendas\VendaRepository;
use App\Traits\ConversorMoeda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrcamentoService
{
    use ConversorMoeda;

    public function __construct(
        protected OrcamentoRepository $repository,
        protected VendaRepository $vendaRepository
    ) {}

    public function index(Request $request)
    {
        $orcamentos = $this->repository->index($request);

        return view('orcamento.index', [
            'orcamentos' => $orcamentos,
            'filtros'    => $request->only(['busca', 'situacao', 'data_inicio', 'data_fim']),
        ]);
    }

    public function create()
    {
        return view('orcamento.create');
    }

    public function store(Request $request)
    {
        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            return back()
                ->withErrors(['itens' => 'Adicione pelo menos um item.'])
                ->withInput();
        }

        $totais = $this->calcularTotais($itens, $request);

        if ($totais === null) {
            return back()
                ->withErrors(['desconto' => 'O desconto não pode ser maior que o subtotal dos itens.'])
                ->withInput();
        }

        $orcamento = $this->repository->store([
            'empresa_id'      => auth()->user()->empresa_id,
            'cliente_id'      => $request->cliente_id ?: null,
            'usuario_id'      => auth()->id(),
            'data_orcamento'  => $request->data_orcamento,
            'data_entrega'    => $request->data_entrega ?: null,
            ...$totais,
            'forma_pagamento' => $request->forma_pagamento,
            'observacoes'     => $request->observacoes,
            'situacao'        => 'pendente',
        ], $itens);

        return redirect()->route('orcamento.show', $orcamento)
            ->with('success', 'Orçamento #' . $orcamento->numero . ' cadastrado com sucesso.');
    }

    public function show(Orcamento $orcamento)
    {
        $this->garantirEmpresa($orcamento);
        $orcamento->load(['cliente', 'itens.produto', 'usuario', 'venda']);

        return view('orcamento.show', ['orcamento' => $orcamento]);
    }

    public function pdf(Orcamento $orcamento)
    {
        $this->garantirEmpresa($orcamento);

        $orcamento->load(['cliente', 'itens.produto', 'usuario', 'venda']);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('orcamento.pdf', ['orcamento' => $orcamento])->setPaper('a4', 'portrait');
        return $pdf->stream('orcamento_' . $orcamento->numero . '.pdf');
    }

    public function edit(Orcamento $orcamento)
    {
        $this->garantirEmpresa($orcamento);

        if ($orcamento->situacao !== 'pendente') {
            return redirect()->route('orcamento.show', $orcamento)
                ->with('error', 'Somente orçamentos pendentes podem ser editados.');
        }

        $orcamento->load(['itens.produto', 'cliente']);

        return view('orcamento.edit', ['orcamento' => $orcamento]);
    }

    public function update(Request $request, Orcamento $orcamento)
    {
        $this->garantirEmpresa($orcamento);

        if ($orcamento->situacao !== 'pendente') {
            return redirect()->route('orcamento.show', $orcamento)
                ->with('error', 'Somente orçamentos pendentes podem ser editados.');
        }

        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            return back()
                ->withErrors(['itens' => 'Adicione pelo menos um item.'])
                ->withInput();
        }

        $totais = $this->calcularTotais($itens, $request);

        if ($totais === null) {
            return back()
                ->withErrors(['desconto' => 'O desconto não pode ser maior que o subtotal dos itens.'])
                ->withInput();
        }

        try {
            $this->repository->update($orcamento, [
                'cliente_id'      => $request->cliente_id ?: null,
                'data_orcamento'  => $request->data_orcamento,
                'data_entrega'    => $request->data_entrega ?: null,
                ...$totais,
                'forma_pagamento' => $request->forma_pagamento,
                'observacoes'     => $request->observacoes,
            ], $itens);
        } catch (\RuntimeException $e) {
            return redirect()->route('orcamento.show', $orcamento)->with('error', $e->getMessage());
        }

        return redirect()->route('orcamento.show', $orcamento)
            ->with('success', 'Orçamento #' . $orcamento->numero . ' atualizado com sucesso.');
    }

    public function aprovar(Orcamento $orcamento)
    {
        $this->garantirEmpresa($orcamento);

        if ($orcamento->situacao !== 'pendente') {
            return redirect()->route('orcamento.show', $orcamento)
                ->with('error', 'Somente orçamentos pendentes podem ser aprovados.');
        }

        try {
            $venda = DB::transaction(function () use ($orcamento) {
            $orcamento = $this->repository->travarPendente($orcamento);
            $orcamento->load('itens');

            $itens = [];
            foreach ($orcamento->itens as $item) {
                $itens[] = [
                    'produto_id'     => $item->produto_id,
                    'produto_nome'   => $item->produto_nome,
                    'produto_codigo' => $item->produto_codigo,
                    'quantidade'     => $item->quantidade,
                    'preco_unitario' => $item->preco_unitario,
                    'desconto'       => $item->desconto,
                    'total'          => $item->total,
                ];
            }

            $venda = $this->vendaRepository->store([
                'empresa_id'      => $orcamento->empresa_id,
                'cliente_id'      => $orcamento->cliente_id,
                'usuario_id'      => auth()->id(),
                'numero'          => $this->vendaRepository->proximoNumero($orcamento->empresa_id),
                'data_venda'      => $orcamento->data_orcamento,
                'data_entrega'    => $orcamento->data_entrega,
                'subtotal'        => $orcamento->subtotal,
                'desconto'        => $orcamento->desconto,
                'acrescimo'       => $orcamento->acrescimo,
                'total'           => $orcamento->total,
                'forma_pagamento' => $orcamento->forma_pagamento,
                'observacoes'     => $orcamento->observacoes,
                'situacao'        => 'em_andamento',
            ], $itens);

            // Se forma à vista, confirma a venda gerada
            if (FormaPagamento::pagamentoIntegral($orcamento->forma_pagamento, (int) $orcamento->empresa_id)) {
                $this->vendaRepository->confirmar($venda->load('itens'));
            }

            $orcamento->update([
                'situacao' => 'aprovado',
                'venda_id' => $venda->id,
            ]);

            return $venda;
            });
        } catch (\Exception $ex) {
            // tratar exceções de confirmação/estoque e retornar ao orçamento com erro amigável
            return redirect()->route('orcamento.show', $orcamento)
                ->with('error', $ex->getMessage());
        }

        return redirect()->route('venda.show', $venda)
            ->with('success', 'Orçamento #' . $orcamento->numero . ' aprovado. Venda #' . $venda->numero . ' gerada.');
    }

    public function recusar(Orcamento $orcamento)
    {
        $this->garantirEmpresa($orcamento);

        if ($orcamento->situacao !== 'pendente') {
            return redirect()->route('orcamento.show', $orcamento)
                ->with('error', 'Somente orçamentos pendentes podem ser recusados.');
        }

        try {
            $this->repository->recusar($orcamento);
        } catch (\RuntimeException $e) {
            return redirect()->route('orcamento.show', $orcamento)->with('error', $e->getMessage());
        }

        return redirect()->route('orcamento.show', $orcamento)
            ->with('success', 'Orçamento #' . $orcamento->numero . ' recusado.');
    }

    public function cancelar(Orcamento $orcamento)
    {
        $this->garantirEmpresa($orcamento);

        if ($orcamento->situacao === 'cancelado') {
            return redirect()->route('orcamento.show', $orcamento)
                ->with('error', 'Orçamento já está cancelado.');
        }

        if ($orcamento->situacao === 'aprovado') {
            return redirect()->route('orcamento.show', $orcamento)
                ->with('error', 'Orçamento aprovado não pode ser cancelado.');
        }

        try {
            $this->repository->cancelar($orcamento);
        } catch (\RuntimeException $e) {
            return redirect()->route('orcamento.show', $orcamento)->with('error', $e->getMessage());
        }

        return redirect()->route('orcamento.show', $orcamento)
            ->with('success', 'Orçamento #' . $orcamento->numero . ' cancelado.');
    }

    public function destroy(Orcamento $orcamento)
    {
        $this->garantirEmpresa($orcamento);

        $numero = $orcamento->numero;

        try {
            $this->repository->destroy($orcamento->id);
        } catch (\RuntimeException $e) {
            return redirect()->route('orcamento.show', $orcamento)->with('error', $e->getMessage());
        }

        return redirect()->route('orcamento.index')
            ->with('success', 'Orçamento #' . $numero . ' excluído com sucesso.');
    }

    protected function garantirEmpresa(Orcamento $orcamento): void
    {
        if ((int) $orcamento->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }

    protected function processarItens(array $itens): array
    {
        $resultado = [];

        foreach ($itens as $item) {
            if (empty($item['produto_id'])) {
                continue;
            }

            $produto = Produto::find($item['produto_id']);

            if (!$produto) {
                continue;
            }
            // garantir que o produto pertence à mesma empresa do usuário
            if ((int) $produto->empresa_id !== (int) auth()->user()->empresa_id) {
                continue;
            }

            $quantidade = $this->parseQuantidade($item['quantidade'] ?? 0);
            if ($quantidade <= 0) {
                continue;
            }
            $precoUnit  = $this->parseDecimal($item['preco_unitario'] ?? 0, 4);
            $descItem   = $this->parseMoeda($item['desconto'] ?? 0);
            $total      = max(0, round(($quantidade * $precoUnit) - $descItem, 2));

            $resultado[] = [
                'produto_id'     => $produto->id,
                'produto_nome'   => $produto->nome,
                'produto_codigo' => $produto->codigo,
                'quantidade'     => $quantidade,
                'preco_unitario' => $precoUnit,
                'desconto'       => $descItem,
                'total'          => $total,
            ];
        }

        return $resultado;
    }

    /**
     * Totais sempre recalculados a partir dos itens; retorna null se o desconto exceder o subtotal.
     */
    protected function calcularTotais(array $itens, Request $request): ?array
    {
        $subtotal  = round(array_sum(array_column($itens, 'total')), 2);
        $desconto  = $this->parseMoeda($request->desconto);
        $acrescimo = $this->parseMoeda($request->acrescimo);

        if ($desconto < 0 || $acrescimo < 0 || $desconto > $subtotal) {
            return null;
        }

        return [
            'subtotal'  => $subtotal,
            'desconto'  => $desconto,
            'acrescimo' => $acrescimo,
            'total'     => round($subtotal - $desconto + $acrescimo, 2),
        ];
    }
}
