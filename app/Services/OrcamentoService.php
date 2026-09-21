<?php

namespace App\Services;

use App\Models\FormaPagamento;
use App\Models\Orcamento;
use App\Models\Produto;
use App\Repositories\OrcamentoRepository;
use App\Repositories\VendaRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrcamentoService
{
    public function __construct(
        protected OrcamentoRepository $repository,
        protected VendaRepository $vendaRepository
    ) {}

    public function index()
    {
        $orcamentos = $this->repository->index();

        return view('orcamento.index', ['orcamentos' => $orcamentos]);
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

        $empresaId = auth()->user()->empresa_id;

        $orcamento = $this->repository->store([
            'empresa_id'      => $empresaId,
            'cliente_id'      => $request->cliente_id ?: null,
            'usuario_id'      => auth()->id(),
            'numero'          => $this->repository->proximoNumero($empresaId),
            'data_orcamento'  => $request->data_orcamento,
            'data_entrega'    => $request->data_entrega ?: null,
            'subtotal'        => $this->parseMoeda($request->subtotal),
            'desconto'        => $this->parseMoeda($request->desconto),
            'acrescimo'       => $this->parseMoeda($request->acrescimo),
            'total'           => $this->parseMoeda($request->total),
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

        $this->repository->update($orcamento, [
            'cliente_id'      => $request->cliente_id ?: null,
            'data_orcamento'  => $request->data_orcamento,
            'data_entrega'    => $request->data_entrega ?: null,
            'subtotal'        => $this->parseMoeda($request->subtotal),
            'desconto'        => $this->parseMoeda($request->desconto),
            'acrescimo'       => $this->parseMoeda($request->acrescimo),
            'total'           => $this->parseMoeda($request->total),
            'forma_pagamento' => $request->forma_pagamento,
            'observacoes'     => $request->observacoes,
            'situacao'        => 'pendente',
        ], $itens);

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

        $orcamento->load('itens');

        try {
            $venda = DB::transaction(function () use ($orcamento) {
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

        $this->repository->cancelar($orcamento);

        return redirect()->route('orcamento.show', $orcamento)
            ->with('success', 'Orçamento #' . $orcamento->numero . ' cancelado.');
    }

    public function destroy(Orcamento $orcamento)
    {
        $this->garantirEmpresa($orcamento);

        $numero = $orcamento->numero;
        $this->repository->destroy($orcamento->id);

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

            $quantidade = $this->parseMoeda($item['quantidade'] ?? 0);
            if ($quantidade <= 0) {
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
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }

        return round((float) $str, 2);
    }
}
