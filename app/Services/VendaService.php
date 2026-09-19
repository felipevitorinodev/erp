<?php

namespace App\Services;

use App\Models\ContaReceber;
use App\Models\FormaPagamento;
use App\Models\Produto;
use App\Models\Venda;
use App\Repositories\VendaRepository;
use Illuminate\Http\Request;

class VendaService
{
    public function __construct(
        protected VendaRepository $repository
    ) {}

    public function index()
    {
        $vendas = $this->repository->index();

        return view('venda.index', ['vendas' => $vendas]);
    }

    public function create()
    {
        return view('venda.create');
    }

    public function store(Request $request)
    {
        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            return back()
                ->withErrors(['itens' => 'Adicione pelo menos um item.'])
                ->withInput();
        }

        $empresaId = auth()->user()->empresa_id;
        $situacao  = $request->situacao ?? 'em_andamento';

        // Forma à vista = pagamento do valor completo → confirma automaticamente
        if (FormaPagamento::pagamentoIntegral($request->forma_pagamento, $empresaId)) {
            $situacao = 'confirmada';
        }

        $dados = [
            'empresa_id'      => $empresaId,
            'cliente_id'      => $request->cliente_id ?: null,
            'usuario_id'      => auth()->id(),
            'numero'          => $this->repository->proximoNumero($empresaId),
            'data_venda'      => $request->data_venda,
            'data_entrega'    => $request->data_entrega ?: null,
            'subtotal'        => $this->parseMoeda($request->subtotal),
            'desconto'        => $this->parseMoeda($request->desconto),
            'acrescimo'       => $this->parseMoeda($request->acrescimo),
            'total'           => $this->parseMoeda($request->total),
            'forma_pagamento' => $request->forma_pagamento,
            'observacoes'     => $request->observacoes,
            'situacao'        => $situacao === 'confirmada' ? 'em_andamento' : $situacao,
        ];

        $venda = $this->repository->store($dados, $itens);

        if ($situacao === 'confirmada') {
            $this->repository->confirmar($venda->load('itens'));

            $mensagem = 'Venda #' . $venda->numero . ' cadastrada e confirmada com sucesso.';
            if (ContaReceber::where('venda_id', $venda->id)->exists()) {
                $mensagem .= ' Conta a receber gerada automaticamente (venda a prazo).';
            } elseif (FormaPagamento::pagamentoIntegral($request->forma_pagamento, $empresaId)) {
                $mensagem .= ' Pagamento integral (à vista).';
            }

            return redirect()->route('venda.show', $venda)->with('success', $mensagem);
        }

        return redirect()->route('venda.show', $venda)
            ->with('success', 'Venda #' . $venda->numero . ' cadastrada com sucesso.');
    }

    public function show(Venda $venda)
    {
        $venda->load(['cliente', 'itens.produto', 'usuario']);

        return view('venda.show', ['venda' => $venda]);
    }

    public function edit(Venda $venda)
    {
        if ($venda->situacao === 'cancelada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda cancelada não pode ser editada.');
        }

        $venda->load(['itens.produto', 'cliente']);

        return view('venda.edit', ['venda' => $venda]);
    }

    public function update(Request $request, Venda $venda)
    {
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

        // Forma à vista = pagamento do valor completo → confirma automaticamente
        if ($pagamentoIntegral) {
            $situacao = 'confirmada';
        }

        $jaConfirmada = $venda->situacao === 'confirmada';
        $deveConfirmar = $situacao === 'confirmada' && !$jaConfirmada;

        $dados = [
            'cliente_id'      => $request->cliente_id ?: null,
            'data_venda'      => $request->data_venda,
            'data_entrega'    => $request->data_entrega ?: null,
            'subtotal'        => $this->parseMoeda($request->subtotal),
            'desconto'        => $this->parseMoeda($request->desconto),
            'acrescimo'       => $this->parseMoeda($request->acrescimo),
            'total'           => $this->parseMoeda($request->total),
            'forma_pagamento' => $request->forma_pagamento,
            'observacoes'     => $request->observacoes,
            'situacao'        => $deveConfirmar ? $venda->situacao : $situacao,
        ];

        $this->repository->update($venda, $dados, $itens);

        if ($deveConfirmar) {
            $this->repository->confirmar($venda->fresh('itens'));
        }

        $mensagem = 'Venda #' . $venda->numero . ' atualizada com sucesso.';
        if ($deveConfirmar && $pagamentoIntegral) {
            $mensagem .= ' Confirmada automaticamente (pagamento integral).';
        } elseif ($deveConfirmar && ContaReceber::where('venda_id', $venda->id)->exists()) {
            $mensagem .= ' Conta a receber gerada automaticamente (venda a prazo).';
        }

        return redirect()->route('venda.show', $venda)
            ->with('success', $mensagem);
    }

    public function confirmar(Venda $venda)
    {
        if ($venda->situacao !== 'em_andamento') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Somente vendas em andamento podem ser confirmadas.');
        }

        $this->repository->confirmar($venda->load('itens'));

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
        if ($venda->situacao === 'cancelada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda já está cancelada.');
        }

        $this->repository->cancelar($venda->load('itens'));

        return redirect()->route('venda.show', $venda)
            ->with('success', 'Venda #' . $venda->numero . ' cancelada.');
    }

    public function destroy(Venda $venda)
    {
        $numero = $venda->numero;
        $venda->load('itens');
        $this->repository->destroy($venda->id);

        return redirect()->route('venda.index')
            ->with('success', 'Venda #' . $numero . ' excluída com sucesso.');
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

            $quantidade = (float) str_replace(',', '.', $item['quantidade'] ?? 1);
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

    protected function parseMoeda(mixed $valor): float
    {
        if (is_null($valor) || $valor === '') {
            return 0.0;
        }

        $str = trim((string) $valor);

        if (str_contains($str, ',') && str_contains($str, '.')) {
            $str = str_replace('.', '', $str); 
            $str = str_replace(',', '.', $str); 
        }
        elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }
        return round((float) $str, 2);
    }
}
