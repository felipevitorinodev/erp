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

        // validar quantidades (devem ser > 0)
        if (is_array($request->itens)) {
            foreach ($request->itens as $idx => $it) {
                $q = $this->parseMoeda($it['quantidade'] ?? 0);
                if ($q <= 0) {
                    return back()
                        ->withErrors(['itens.' . $idx . '.quantidade' => 'A quantidade deve ser maior que zero.'])
                        ->withInput();
                }
            }
        }

        $empresaId = auth()->user()->empresa_id;
        $situacao  = $request->situacao ?? 'em_andamento';

        // Forma à vista = pagamento do valor completo → confirma automaticamente
        if (FormaPagamento::pagamentoIntegral($request->forma_pagamento, $empresaId)) {
            $situacao = 'confirmada';
        }

        // se for confirmar imediatamente, validar estoque proibindo zerar saldo
        if ($situacao === 'confirmada') {
            $insuf = $this->verificarEstoqueParaConfirmacao($itens);
            if (!empty($insuf)) {
                // montar mensagens por item
                $errors = [];
                foreach ($insuf as $i => $p) {
                    $errors['itens.' . $i . '.quantidade'] = "Produto \"{$p['nome']}\" possui estoque {$p['disponivel']} — venda que zeraria/ultrapassaria não permitida.";
                }
                return back()->withErrors($errors)->withInput();
            }
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
            try {
                $this->repository->confirmar($venda->load('itens'));
                $mensagem = 'Venda #' . $venda->numero . ' cadastrada e confirmada com sucesso.';
            } catch (\Exception $ex) {
                // confirmação falhou por falta de estoque — redireciona mostrando erro
                return redirect()->route('venda.show', $venda)
                    ->with('error', $ex->getMessage());
            }
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
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        $venda->load(['cliente', 'itens.produto', 'usuario']);

        return view('venda.show', ['venda' => $venda]);
    }

    public function pdf(Venda $venda)
    {
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        $venda->load(['cliente', 'itens.produto', 'usuario']);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('venda.pdf', ['venda' => $venda])->setPaper('a4', 'portrait');
        return $pdf->stream('venda_' . $venda->numero . '.pdf');
    }

    public function edit(Venda $venda)
    {
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        if ($venda->situacao === 'cancelada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda cancelada não pode ser editada.');
        }

        $venda->load(['itens.produto', 'cliente']);

        return view('venda.edit', ['venda' => $venda]);
    }

    public function update(Request $request, Venda $venda)
    {
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

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
        // validar quantidades (devem ser > 0)
        if (is_array($request->itens)) {
            foreach ($request->itens as $idx => $it) {
                $q = $this->parseMoeda($it['quantidade'] ?? 0);
                if ($q <= 0) {
                    return back()
                        ->withErrors(['itens.' . $idx . '.quantidade' => 'A quantidade deve ser maior que zero.'])
                        ->withInput();
                }
            }
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
            // validar estoque proibindo zerar saldo antes de confirmar
            $insuf = $this->verificarEstoqueParaConfirmacao($itens);
            if (!empty($insuf)) {
                $errors = [];
                foreach ($insuf as $i => $p) {
                    $errors['itens.' . $i . '.quantidade'] = "Produto \"{$p['nome']}\" possui estoque {$p['disponivel']} — venda que zeraria/ultrapassaria não permitida.";
                }
                return back()->withErrors($errors)->withInput();
            }

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

        return redirect()->route('venda.show', $venda)
            ->with('success', $mensagem);
    }

    public function confirmar(Venda $venda)
    {
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

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
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

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
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        $numero = $venda->numero;
        $venda->load('itens');
        $this->repository->destroy($venda->id);

        return redirect()->route('venda.index')
            ->with('success', 'Venda #' . $numero . ' excluída com sucesso.');
    }

    /**
     * Verifica se os itens excedem o estoque ou zerariam o estoque quando controlado.
     * Retorna array de itens insuficientes no formato [idx => ['nome'=>..., 'disponivel'=>...], ...]
     */
    protected function verificarEstoqueParaConfirmacao(array $itens): array
    {
        $insuficientes = [];
        foreach ($itens as $idx => $item) {
            if (empty($item['produto_id'])) continue;
            $produto = Produto::find($item['produto_id']);
            if (!$produto) continue;
            if (!$produto->controla_estoque) continue;

            $disponivel = (float) $produto->estoque_atual;
            $quantidade = (float) $item['quantidade'];

            // bloquear se a confirmação zeraria ou excederia o estoque
            if ($quantidade >= $disponivel) {
                $insuficientes[$idx] = [
                    'nome' => $produto->nome,
                    'disponivel' => $disponivel
                ];
            }
        }
        return $insuficientes;
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

            $quantidade = $this->parseMoeda($item['quantidade'] ?? 0);
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
