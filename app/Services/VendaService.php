<?php

namespace App\Services;

use App\Models\Cliente;
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
        return view('venda.create', $this->dadosParaForm());
    }

    public function store(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;
        $situacao  = $request->situacao ?? 'orcamento';

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
            'situacao'        => $situacao,
        ];

        $itens = $this->processarItens($request->itens ?? []);

        // Salva como orçamento primeiro para poder aplicar estoque depois
        $dadosStore           = $dados;
        $dadosStore['situacao'] = 'orcamento';

        $venda = $this->repository->store($dadosStore, $itens);

        // Se veio como confirmada, confirma agora (aplica estoque)
        if ($situacao === 'confirmada') {
            $this->repository->confirmar($venda->load('itens'));
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

        $venda->load('itens.produto');

        return view('venda.edit', array_merge(
            ['venda' => $venda],
            $this->dadosParaForm()
        ));
    }

    public function update(Request $request, Venda $venda)
    {
        if ($venda->situacao === 'cancelada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda cancelada não pode ser editada.');
        }

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
            'situacao'        => $request->situacao ?? $venda->situacao,
        ];

        $itens = $this->processarItens($request->itens ?? []);

        $this->repository->update($venda, $dados, $itens);

        return redirect()->route('venda.show', $venda)
            ->with('success', 'Venda #' . $venda->numero . ' atualizada com sucesso.');
    }

    public function confirmar(Venda $venda)
    {
        if ($venda->situacao !== 'orcamento') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Somente orçamentos podem ser confirmados.');
        }

        $this->repository->confirmar($venda->load('itens'));

        return redirect()->route('venda.show', $venda)
            ->with('success', 'Venda #' . $venda->numero . ' confirmada com sucesso.');
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

    // -------------------------------------------------------------------------

    protected function dadosParaForm(): array
    {
        return [
            'clientes' => Cliente::where('ativo', true)->orderBy('nome')->get(),
            'produtos' => Produto::where('ativo', true)->orderBy('nome')
                ->with('unidadeMedida')
                ->get(),
        ];
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

        // Suporta "1.234,56" (pt-BR) e "1234.56" (padrão)
        $str = str_replace('.', '', (string) $valor);
        $str = str_replace(',', '.', $str);

        return (float) $str;
    }
}
