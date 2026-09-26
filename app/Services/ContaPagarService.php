<?php

namespace App\Services;

use App\Http\Requests\ContaPagarRequest;
use App\Models\ContaPagar;
use App\Repositories\CategoriaFinanceiraRepository;
use App\Repositories\ContaPagarRepository;
use App\Traits\ConversorMoeda;
use Illuminate\Http\Request;

class ContaPagarService
{
    use ConversorMoeda;

    public function __construct(
        protected ContaPagarRepository $repository,
        protected CategoriaFinanceiraRepository $categoriaFinanceiraRepository
    ) {}

    public function index(Request $request)
    {
        $contas = $this->repository->index($request);

        return view('conta-pagar.index', [
            'contas'  => $contas,
            'filtros' => $request->only(['busca', 'situacao', 'data_vencimento']),
        ]);
    }

    public function create()
    {
        return view('conta-pagar.create', [
            'categorias' => $this->categoriaFinanceiraRepository->listarAtivasPorTipo('despesa'),
        ]);
    }

    public function store(ContaPagarRequest $request)
    {
        $dados = $this->montarDados($request);
        $dados['empresa_id'] = auth()->user()->empresa_id;
        $dados['valor_pago'] = 0;
        $dados['situacao']   = 'aberta';

        $conta = $this->repository->store($dados);

        return redirect()->route('conta-pagar.show', $conta)
            ->with('success', 'Conta a pagar cadastrada com sucesso.');
    }

    public function show(ContaPagar $contaPagar)
    {
        $this->garantirEmpresa($contaPagar);
        $contaPagar->load(['fornecedor', 'categoria', 'entradaEstoque']);

        return view('conta-pagar.show', ['conta' => $contaPagar]);
    }

    public function edit(ContaPagar $contaPagar)
    {
        $this->garantirEmpresa($contaPagar);

        if (in_array($contaPagar->situacao, ['paga', 'cancelada'], true)) {
            return redirect()->route('conta-pagar.show', $contaPagar)
                ->with('error', 'Conta paga ou cancelada não pode ser editada.');
        }

        $contaPagar->load(['fornecedor', 'categoria']);

        $categorias = $this->categoriaFinanceiraRepository->listarAtivasPorTipo('despesa');
        if ($contaPagar->categoria_id && !$categorias->contains('id', $contaPagar->categoria_id) && $contaPagar->categoria) {
            $categorias->push($contaPagar->categoria);
            $categorias = $categorias->sortBy('nome')->values();
        }

        return view('conta-pagar.edit', [
            'conta'       => $contaPagar,
            'categorias'  => $categorias,
        ]);
    }

    public function update(ContaPagarRequest $request, ContaPagar $contaPagar)
    {
        $this->garantirEmpresa($contaPagar);

        if (in_array($contaPagar->situacao, ['paga', 'cancelada'], true)) {
            return redirect()->route('conta-pagar.show', $contaPagar)
                ->with('error', 'Conta paga ou cancelada não pode ser editada.');
        }

        $dados = $this->montarDados($request);
        $this->repository->update($contaPagar, $dados);

        return redirect()->route('conta-pagar.show', $contaPagar)
            ->with('success', 'Conta a pagar atualizada com sucesso.');
    }

    public function pagar(Request $request, ContaPagar $contaPagar)
    {
        $this->garantirEmpresa($contaPagar);

        if (in_array($contaPagar->situacao, ['paga', 'cancelada'], true)) {
            return redirect()->route('conta-pagar.show', $contaPagar)
                ->with('error', 'Não é possível pagar nesta conta.');
        }

        $valorInformado = $this->parseMoeda($request->valor_pago);
        $saldo          = (float) $contaPagar->valor - (float) $contaPagar->valor_pago;

        if ($valorInformado <= 0) {
            return back()
                ->withErrors(['valor_pago' => 'O valor pago deve ser maior que zero.'])
                ->withInput();
        }

        if ($valorInformado > round($saldo, 2)) {
            return back()
                ->withErrors(['valor_pago' => 'O valor pago não pode exceder o saldo restante.'])
                ->withInput();
        }

        if (!$request->filled('data_pagamento')) {
            return back()
                ->withErrors(['data_pagamento' => 'A data de pagamento é obrigatória.'])
                ->withInput();
        }

        $novoValorPago = round((float) $contaPagar->valor_pago + $valorInformado, 2);
        $valorTotal    = round((float) $contaPagar->valor, 2);
        $situacao      = $novoValorPago >= $valorTotal ? 'paga' : 'parcial';

        // Sempre grava data_pagamento (parcial e paga) — o fluxo de caixa depende disso
        $dados = [
            'valor_pago'      => $novoValorPago,
            'situacao'        => $situacao,
            'forma_pagamento' => $request->forma_pagamento ?: $contaPagar->forma_pagamento,
            'data_pagamento'  => $request->data_pagamento,
        ];

        $this->repository->pagar($contaPagar, $dados);

        return redirect()->route('conta-pagar.show', $contaPagar)
            ->with('success', 'Pagamento registrado com sucesso.');
    }

    public function cancelar(ContaPagar $contaPagar)
    {
        $this->garantirEmpresa($contaPagar);

        if ($contaPagar->situacao === 'cancelada') {
            return redirect()->route('conta-pagar.show', $contaPagar)
                ->with('error', 'Conta já está cancelada.');
        }

        if ($contaPagar->situacao === 'paga') {
            return redirect()->route('conta-pagar.show', $contaPagar)
                ->with('error', 'Conta paga não pode ser cancelada.');
        }

        $this->repository->cancelar($contaPagar);

        return redirect()->route('conta-pagar.show', $contaPagar)
            ->with('success', 'Conta a pagar cancelada.');
    }

    public function destroy(ContaPagar $contaPagar)
    {
        $this->garantirEmpresa($contaPagar);

        if ($contaPagar->entrada_estoque_id) {
            return redirect()->route('conta-pagar.show', $contaPagar)
                ->with('error', 'Conta vinculada a uma entrada de estoque não pode ser excluída. Cancele a entrada ou a conta.');
        }

        if (in_array($contaPagar->situacao, ['paga', 'parcial'], true)) {
            return redirect()->route('conta-pagar.show', $contaPagar)
                ->with('error', 'Conta com pagamento registrado não pode ser excluída. Cancele-a primeiro, se permitido.');
        }

        $this->repository->destroy($contaPagar->id);

        return redirect()->route('conta-pagar.index')
            ->with('success', 'Conta a pagar excluída com sucesso.');
    }

    protected function montarDados(ContaPagarRequest $request): array
    {
        return [
            'fornecedor_id'   => $request->fornecedor_id ?: null,
            'descricao'       => $request->descricao,
            'valor'           => $this->parseMoeda($request->valor),
            'data_vencimento' => $request->data_vencimento,
            'forma_pagamento' => $request->forma_pagamento,
            'categoria_id'    => $request->categoria_id ?: null,
            'observacoes'     => $request->observacoes,
        ];
    }

    protected function garantirEmpresa(ContaPagar $conta): void
    {
        if ((int) $conta->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }
}
