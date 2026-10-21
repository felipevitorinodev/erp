<?php

namespace App\Services\OrdensServico;

use App\Models\FormasPagamento\FormaPagamento;
use App\Models\OrdensServico\OrdemServico;
use App\Models\Produtos\Produto;
use App\Repositories\OrdensServico\OrdemServicoRepository;
use App\Repositories\Vendas\VendaRepository;
use App\Traits\ConversorMoeda;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdemServicoService
{
    use ConversorMoeda;

    public function __construct(
        protected OrdemServicoRepository $repository,
        protected VendaRepository $vendaRepository
    ) {}

    public function index(Request $request)
    {
        return view('ordem-servico.index', [
            'ordens'  => $this->repository->index($request),
            'filtros' => $request->only(['busca', 'situacao', 'data_inicio', 'data_fim']),
        ]);
    }

    public function create()
    {
        return view('ordem-servico.create', ['itensIniciais' => $this->itensIniciais()]);
    }

    public function store(Request $request)
    {
        $itens = $this->processarItens($request->itens ?? []);
        $totais = $this->calcularTotais($itens, $request);

        if ($totais === null) {
            return back()
                ->withErrors(['desconto' => 'O desconto não pode ser maior que o subtotal dos itens.'])
                ->withInput();
        }

        $os = $this->repository->store([
            'empresa_id' => auth()->user()->empresa_id,
            'usuario_id' => auth()->id(),
            ...$this->dadosFormulario($request),
            ...$totais,
            'situacao'   => 'aberta',
        ], $itens);

        return redirect()->route('ordem-servico.show', $os)
            ->with('success', 'Ordem de serviço #' . $os->numero . ' aberta com sucesso.');
    }

    public function show(OrdemServico $os)
    {
        $this->garantirEmpresa($os);
        $os->load(['cliente', 'tecnico', 'itens.produto', 'usuario', 'venda', 'orcamento']);

        return view('ordem-servico.show', ['os' => $os]);
    }

    public function pdf(OrdemServico $os)
    {
        $this->garantirEmpresa($os);
        $os->load(['cliente', 'tecnico', 'itens', 'usuario', 'venda', 'empresa']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('ordem-servico.pdf', ['os' => $os])->setPaper('a4', 'portrait');

        return $pdf->stream('ordem_servico_' . $os->numero . '.pdf');
    }

    public function edit(OrdemServico $os)
    {
        $this->garantirEmpresa($os);

        if (! $os->estaAberta()) {
            return redirect()->route('ordem-servico.show', $os)
                ->with('error', 'Ordem de serviço ' . mb_strtolower($os->situacaoLabel()) . ' não pode ser editada.');
        }

        $os->load(['itens.produto', 'cliente', 'tecnico']);

        return view('ordem-servico.edit', ['os' => $os, 'itensIniciais' => $this->itensIniciais($os)]);
    }

    /**
     * Itens para pré-preencher o formulário: o que voltou do old() após erro de validação ou os da própria OS.
     */
    protected function itensIniciais(?OrdemServico $os = null): array
    {
        $antigos = old('itens');

        if (is_array($antigos)) {
            $nomes = Produto::where('empresa_id', auth()->user()->empresa_id)
                ->whereIn('id', array_filter(array_column($antigos, 'produto_id')))
                ->pluck('nome', 'id');

            return collect($antigos)
                ->filter(fn ($item) => ! empty($item['produto_id']) && isset($nomes[$item['produto_id']]))
                ->map(fn ($item) => [
                    'produto_id'     => $item['produto_id'],
                    'produto_nome'   => $nomes[$item['produto_id']],
                    'quantidade'     => (float) ($item['quantidade'] ?? 1),
                    'preco_unitario' => (float) ($item['preco_unitario'] ?? 0),
                    'desconto'       => (float) ($item['desconto'] ?? 0),
                ])->values()->all();
        }

        if (! $os) {
            return [];
        }

        return $os->itens->map(fn ($item) => [
            'produto_id'     => $item->produto_id,
            'produto_nome'   => $item->produto_nome,
            'quantidade'     => (float) $item->quantidade,
            'preco_unitario' => (float) $item->preco_unitario,
            'desconto'       => (float) $item->desconto,
        ])->all();
    }

    public function update(Request $request, OrdemServico $os)
    {
        $this->garantirEmpresa($os);

        $itens = $this->processarItens($request->itens ?? []);
        $totais = $this->calcularTotais($itens, $request);

        if ($totais === null) {
            return back()
                ->withErrors(['desconto' => 'O desconto não pode ser maior que o subtotal dos itens.'])
                ->withInput();
        }

        try {
            $this->repository->update($os, [...$this->dadosFormulario($request), ...$totais], $itens);
        } catch (\RuntimeException $e) {
            return redirect()->route('ordem-servico.show', $os)->with('error', $e->getMessage());
        }

        return redirect()->route('ordem-servico.show', $os)
            ->with('success', 'Ordem de serviço #' . $os->numero . ' atualizada com sucesso.');
    }

    public function alterarSituacao(Request $request, OrdemServico $os)
    {
        $this->garantirEmpresa($os);

        $situacao = (string) $request->input('situacao');
        if (! in_array($situacao, OrdemServico::SITUACOES_ABERTAS, true)) {
            return redirect()->route('ordem-servico.show', $os)->with('error', 'Situação inválida.');
        }

        try {
            $os = $this->repository->alterarSituacao($os, $situacao);
        } catch (\RuntimeException $e) {
            return redirect()->route('ordem-servico.show', $os)->with('error', $e->getMessage());
        }

        return redirect()->route('ordem-servico.show', $os)
            ->with('success', 'Ordem de serviço #' . $os->numero . ': ' . mb_strtolower($os->situacaoLabel()) . '.');
    }

    /**
     * Gera a venda a partir das peças/serviços da OS. Forma à vista confirma a venda (baixa estoque e
     * lança o recebimento); a prazo deixa a venda em andamento, como na aprovação de orçamento.
     */
    public function faturar(OrdemServico $os)
    {
        $this->garantirEmpresa($os);

        if (! $os->estaAberta()) {
            return redirect()->route('ordem-servico.show', $os)
                ->with('error', 'Esta ordem de serviço já foi encerrada.');
        }

        try {
            $venda = DB::transaction(function () use ($os) {
                $os = $this->repository->travarAberta($os);
                $os->load('itens');

                if ($os->itens->isEmpty()) {
                    throw new \RuntimeException('Adicione ao menos uma peça ou serviço antes de faturar a OS.');
                }

                $itens = $os->itens->map(fn ($item) => [
                    'produto_id'     => $item->produto_id,
                    'produto_nome'   => $item->produto_nome,
                    'produto_codigo' => $item->produto_codigo,
                    'quantidade'     => $item->quantidade,
                    'preco_unitario' => $item->preco_unitario,
                    'desconto'       => $item->desconto,
                    'total'          => $item->total,
                ])->all();

                $observacoes = trim('OS #' . $os->numero . ($os->equipamento ? ' - ' . $os->equipamento : '')
                    . "\n" . (string) $os->observacoes);

                $venda = $this->vendaRepository->store([
                    'empresa_id'      => $os->empresa_id,
                    'cliente_id'      => $os->cliente_id,
                    'usuario_id'      => auth()->id(),
                    'funcionario_id'  => $os->funcionario_id,
                    'numero'          => $this->vendaRepository->proximoNumero($os->empresa_id),
                    'data_venda'      => now()->toDateString(),
                    'subtotal'        => $os->subtotal,
                    'desconto'        => $os->desconto,
                    'acrescimo'       => $os->acrescimo,
                    'total'           => $os->total,
                    'forma_pagamento' => $os->forma_pagamento,
                    'observacoes'     => $observacoes,
                    'situacao'        => 'em_andamento',
                ], $itens);

                // A OS precisa estar vinculada antes de confirmar: a comissão do técnico usa a regra de OS.
                $os->update([
                    'situacao'       => 'faturada',
                    'venda_id'       => $venda->id,
                    'data_conclusao' => $os->data_conclusao ?? now()->toDateString(),
                ]);

                if (FormaPagamento::pagamentoIntegral($os->forma_pagamento, (int) $os->empresa_id)) {
                    $this->vendaRepository->confirmar($venda->load('itens'));
                }

                return $venda;
            });
        } catch (QueryException $e) {
            report($e);

            return redirect()->route('ordem-servico.show', $os)
                ->with('error', 'Não foi possível faturar a ordem de serviço. Tente novamente.');
        } catch (\Exception $e) {
            return redirect()->route('ordem-servico.show', $os)->with('error', $e->getMessage());
        }

        return redirect()->route('venda.show', $venda)
            ->with('success', 'Ordem de serviço #' . $os->numero . ' faturada. Venda #' . $venda->numero . ' gerada.');
    }

    public function cancelar(OrdemServico $os)
    {
        $this->garantirEmpresa($os);

        try {
            $this->repository->cancelar($os);
        } catch (\RuntimeException $e) {
            return redirect()->route('ordem-servico.show', $os)->with('error', $e->getMessage());
        }

        return redirect()->route('ordem-servico.show', $os)
            ->with('success', 'Ordem de serviço #' . $os->numero . ' cancelada.');
    }

    public function destroy(OrdemServico $os)
    {
        $this->garantirEmpresa($os);

        $numero = $os->numero;

        try {
            $this->repository->destroy($os->id);
        } catch (\RuntimeException $e) {
            return redirect()->route('ordem-servico.show', $os)->with('error', $e->getMessage());
        }

        return redirect()->route('ordem-servico.index')
            ->with('success', 'Ordem de serviço #' . $numero . ' excluída com sucesso.');
    }

    protected function dadosFormulario(Request $request): array
    {
        return [
            'cliente_id'        => $request->cliente_id ?: null,
            'funcionario_id'    => $request->funcionario_id ?: null,
            'data_abertura'     => $request->data_abertura,
            'data_previsao'     => $request->data_previsao ?: null,
            'equipamento'       => $request->equipamento,
            'marca'             => $request->marca,
            'modelo'            => $request->modelo,
            'numero_serie'      => $request->numero_serie,
            'acessorios'        => $request->acessorios,
            'defeito_relatado'  => $request->defeito_relatado,
            'diagnostico'       => $request->diagnostico,
            'servico_executado' => $request->servico_executado,
            'garantia'          => $request->garantia,
            'forma_pagamento'   => $request->forma_pagamento,
            'observacoes'       => $request->observacoes,
        ];
    }

    protected function garantirEmpresa(OrdemServico $os): void
    {
        if ((int) $os->empresa_id !== (int) auth()->user()->empresa_id) {
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

            $produto = Produto::where('empresa_id', auth()->user()->empresa_id)->find($item['produto_id']);
            if (! $produto) {
                continue;
            }

            $quantidade = $this->parseQuantidade($item['quantidade'] ?? 0);
            if ($quantidade <= 0) {
                continue;
            }
            $precoUnit = $this->parseDecimal($item['preco_unitario'] ?? 0, 4);
            $descItem  = $this->parseMoeda($item['desconto'] ?? 0);

            $resultado[] = [
                'produto_id'     => $produto->id,
                'produto_nome'   => $produto->nome,
                'produto_codigo' => $produto->codigo,
                'quantidade'     => $quantidade,
                'preco_unitario' => $precoUnit,
                'desconto'       => $descItem,
                'total'          => max(0, round(($quantidade * $precoUnit) - $descItem, 2)),
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
