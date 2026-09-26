<?php

namespace App\Services;

use App\Http\Requests\VendaRequest;
use App\Models\ContaReceber;
use App\Models\FormaPagamento;
use App\Models\Funcionario;
use App\Models\Produto;
use App\Models\Venda;
use App\Repositories\VendaRepository;
use App\Traits\ConversorMoeda;
use Illuminate\Http\Request;

class VendaService
{
    use ConversorMoeda;

    public function __construct(
        protected VendaRepository $repository
    ) {}

    public function index(Request $request)
    {
        $vendas = $this->repository->index($request);

        return view('venda.index', [
            'vendas'  => $vendas,
            'filtros' => $request->only(['busca', 'situacao', 'data_inicio', 'data_fim']),
        ]);
    }

    public function create()
    {
        return view('venda.create');
    }

    public function store(VendaRequest $request)
    {
        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            return back()
                ->withErrors(['itens' => 'Adicione pelo menos um item.'])
                ->withInput();
        }

        $empresaId = auth()->user()->empresa_id;
        $situacao  = $request->situacao ?? 'em_andamento';
        $totais    = $this->calcularTotais($itens, $request->desconto, $request->acrescimo);

        // Forma à vista = pagamento do valor completo → confirma automaticamente
        if (FormaPagamento::pagamentoIntegral($request->forma_pagamento, $empresaId)) {
            $situacao = 'confirmada';
        }

        $avisoEstoque = $this->montarAvisoEstoque($itens);

        $dados = [
            'empresa_id'      => $empresaId,
            'cliente_id'      => $request->cliente_id ?: null,
            'funcionario_id'  => $this->resolverFuncionarioId($request->funcionario_id, $empresaId),
            'usuario_id'      => auth()->id(),
            'numero'          => $this->repository->proximoNumero($empresaId),
            'data_venda'      => $request->data_venda,
            'data_entrega'    => $request->data_entrega ?: null,
            'subtotal'        => $totais['subtotal'],
            'desconto'        => $totais['desconto'],
            'acrescimo'       => $totais['acrescimo'],
            'total'           => $totais['total'],
            'forma_pagamento' => $request->forma_pagamento,
            'observacoes'     => $request->observacoes,
            'situacao'        => $situacao === 'confirmada' ? 'em_andamento' : $situacao,
        ];

        $venda = $this->repository->store($dados, $itens);

        if ($situacao === 'confirmada') {
            try {
                $this->repository->confirmar($venda->load('itens'));
                $mensagem = 'Venda #' . $venda->numero . ' cadastrada e confirmada com sucesso.';
            } catch (\Exception $ex) {
                return redirect()->route('venda.show', $venda)
                    ->with('error', $ex->getMessage());
            }
            if (ContaReceber::where('venda_id', $venda->id)->exists()) {
                $mensagem .= ' Conta a receber gerada automaticamente (venda a prazo).';
            } elseif (FormaPagamento::pagamentoIntegral($request->forma_pagamento, $empresaId)) {
                $mensagem .= ' Pagamento integral (à vista).';
            }

            $redirect = redirect()->route('venda.show', $venda)->with('success', $mensagem);
            if ($avisoEstoque) {
                $redirect = $redirect->with('warning', $avisoEstoque);
            }
            return $redirect;
        }

        $redirect = redirect()->route('venda.show', $venda)
            ->with('success', 'Venda #' . $venda->numero . ' cadastrada com sucesso.');
        if ($avisoEstoque) {
            $redirect = $redirect->with('warning', $avisoEstoque);
        }
        return $redirect;
    }

    public function show(Venda $venda)
    {
        $this->autorizarVenda($venda);

        $venda->load(['cliente', 'funcionario', 'itens.produto', 'usuario']);

        return view('venda.show', ['venda' => $venda]);
    }

    public function pdf(Venda $venda)
    {
        $this->autorizarVenda($venda);

        $venda->load(['cliente', 'funcionario', 'itens.produto', 'usuario']);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('venda.pdf', ['venda' => $venda])->setPaper('a4', 'portrait');
        return $pdf->stream('venda_' . $venda->numero . '.pdf');
    }

    public function edit(Venda $venda)
    {
        $this->autorizarVenda($venda);

        if ($venda->situacao === 'cancelada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda cancelada não pode ser editada.');
        }

        $venda->load(['itens.produto', 'cliente', 'funcionario']);

        return view('venda.edit', ['venda' => $venda]);
    }

    public function update(VendaRequest $request, Venda $venda)
    {
        $this->autorizarVenda($venda);

        if ($venda->situacao === 'cancelada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda cancelada não pode ser editada.');
        }

        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            return back()
                ->withErrors(['itens' => 'Adicione pelo menos um item.'])
                ->withInput();
        }

        $empresaId = (int) $venda->empresa_id;
        $situacao  = $request->situacao ?? $venda->situacao;
        $pagamentoIntegral = FormaPagamento::pagamentoIntegral($request->forma_pagamento, $empresaId);
        $totais = $this->calcularTotais($itens, $request->desconto, $request->acrescimo);

        // Venda já confirmada: não permite rebaixar situação pelo formulário
        if ($venda->situacao === 'confirmada') {
            $situacao = 'confirmada';
        }

        // Forma à vista = pagamento do valor completo → confirma automaticamente
        if ($pagamentoIntegral && $venda->situacao !== 'confirmada') {
            $situacao = 'confirmada';
        }

        $jaConfirmada = $venda->situacao === 'confirmada';
        $deveConfirmar = $situacao === 'confirmada' && !$jaConfirmada;
        $avisoEstoque = $this->montarAvisoEstoque($itens);

        $dados = [
            'cliente_id'      => $request->cliente_id ?: null,
            'funcionario_id'  => $this->resolverFuncionarioId($request->funcionario_id, $empresaId),
            'data_venda'      => $request->data_venda,
            'data_entrega'    => $request->data_entrega ?: null,
            'subtotal'        => $totais['subtotal'],
            'desconto'        => $totais['desconto'],
            'acrescimo'       => $totais['acrescimo'],
            'total'           => $totais['total'],
            'forma_pagamento' => $request->forma_pagamento,
            'observacoes'     => $request->observacoes,
            'situacao'        => $jaConfirmada ? 'confirmada' : ($deveConfirmar ? $venda->situacao : $situacao),
        ];

        $this->repository->update($venda, $dados, $itens);

        if ($deveConfirmar) {
            try {
                $this->repository->confirmar($venda->fresh('itens'));
            } catch (\Exception $ex) {
                return redirect()->route('venda.show', $venda)
                    ->with('error', $ex->getMessage());
            }
        }

        $mensagem = 'Venda #' . $venda->numero . ' atualizada com sucesso.';
        if ($deveConfirmar && $pagamentoIntegral) {
            $mensagem .= ' Confirmada automaticamente (pagamento integral).';
        } elseif ($deveConfirmar && ContaReceber::where('venda_id', $venda->id)->exists()) {
            $mensagem .= ' Conta a receber gerada automaticamente (venda a prazo).';
        }

        $redirect = redirect()->route('venda.show', $venda)->with('success', $mensagem);
        if ($avisoEstoque) {
            $redirect = $redirect->with('warning', $avisoEstoque);
        }
        return $redirect;
    }

    public function confirmar(Venda $venda)
    {
        $this->autorizarVenda($venda);

        if ($venda->situacao !== 'em_andamento') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Somente vendas em andamento podem ser confirmadas.');
        }

        try {
            $this->repository->confirmar($venda->load('itens'));
        } catch (\Exception $ex) {
            return redirect()->route('venda.show', $venda)
                ->with('error', $ex->getMessage());
        }

        $contaGerada = ContaReceber::where('venda_id', $venda->id)->exists();
        $mensagem = 'Venda #' . $venda->numero . ' confirmada com sucesso.';

        if ($contaGerada) {
            $mensagem .= ' Conta a receber gerada automaticamente (venda a prazo).';
        }

        return redirect()->route('venda.show', $venda)
            ->with('success', $mensagem);
    }

    public function cancelar(Venda $venda)
    {
        $this->autorizarVenda($venda);

        if ($venda->situacao === 'cancelada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda já está cancelada.');
        }

        try {
            $this->repository->cancelar($venda->load('itens'));
        } catch (\Exception $ex) {
            return redirect()->route('venda.show', $venda)
                ->with('error', $ex->getMessage());
        }

        return redirect()->route('venda.show', $venda)
            ->with('success', 'Venda #' . $venda->numero . ' cancelada.');
    }

    public function destroy(Venda $venda)
    {
        $this->autorizarVenda($venda);

        $numero = $venda->numero;
        $venda->load('itens');

        try {
            $this->repository->destroy($venda->id);
        } catch (\Exception $ex) {
            return redirect()->route('venda.show', $venda)
                ->with('error', $ex->getMessage());
        }

        return redirect()->route('venda.index')
            ->with('success', 'Venda #' . $numero . ' excluída com sucesso.');
    }

    /**
     * Avisa quando a venda deixaria o estoque negativo (não bloqueia).
     */
    protected function montarAvisoEstoque(array $itens): ?string
    {
        $avisos = [];
        foreach ($itens as $item) {
            if (empty($item['produto_id'])) {
                continue;
            }
            $produto = Produto::find($item['produto_id']);
            if (!$produto || !$produto->controla_estoque) {
                continue;
            }

            $disponivel = (float) $produto->estoque_atual;
            $quantidade = (float) $item['quantidade'];

            if ($quantidade > $disponivel) {
                $avisos[] = "\"{$produto->nome}\" (disp. {$disponivel})";
            }
        }

        if (empty($avisos)) {
            return null;
        }

        return 'Atenção: estoque insuficiente para ' . implode(', ', $avisos) . '. A venda será permitida e o estoque poderá ficar negativo.';
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
                // ignorar itens com quantidade inválida (serão validados antes)
                continue;
            }
            $precoUnit  = $this->parseMoeda($item['preco_unitario'] ?? 0);
            $descItem   = $this->parseMoeda($item['desconto'] ?? 0);
            $total      = max(0, ($quantidade * $precoUnit) - $descItem);

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

    private function calcularTotais(array $itens, mixed $descontoRaw, mixed $acrescimoRaw): array
    {
        $subtotal = round(array_sum(array_column($itens, 'total')), 2);
        $desconto = $this->parseMoeda($descontoRaw);
        $acrescimo = $this->parseMoeda($acrescimoRaw);
        $total = max(0, round($subtotal - $desconto + $acrescimo, 2));

        return compact('subtotal', 'desconto', 'acrescimo', 'total');
    }

    private function autorizarVenda(Venda $venda): void
    {
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }

    private function resolverFuncionarioId($funcionarioId, int $empresaId): ?int
    {
        if (!$funcionarioId) {
            return null;
        }

        $funcionario = Funcionario::where('id', $funcionarioId)
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->first();

        return $funcionario?->id;
    }
}
