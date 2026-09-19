<?php

namespace App\Services;

use App\Http\Requests\ContaPagarRequest;
use App\Models\ContaPagar;
use App\Repositories\ContaPagarRepository;
use Illuminate\Http\Request;

class ContaPagarService
{
    public function __construct(
        protected ContaPagarRepository $repository
    ) {}

    public function index(Request $request)
    {
        $contas = $this->repository->index($request);

        return view('conta-pagar.index', [
            'contas'  => $contas,
            'filtros' => $request->only(['situacao', 'data_vencimento']),
        ]);
    }

    public function create()
    {
        return view('conta-pagar.create');
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
        $contaPagar->load('fornecedor');

        return view('conta-pagar.show', ['conta' => $contaPagar]);
    }

    public function edit(ContaPagar $contaPagar)
    {
        $this->garantirEmpresa($contaPagar);

        if (in_array($contaPagar->situacao, ['paga', 'cancelada'], true)) {
            return redirect()->route('conta-pagar.show', $contaPagar)
                ->with('error', 'Conta paga ou cancelada não pode ser editada.');
        }

        $contaPagar->load('fornecedor');

        return view('conta-pagar.edit', ['conta' => $contaPagar]);
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
        $situacao      = $novoValorPago >= (float) $contaPagar->valor ? 'paga' : 'parcial';

        $dados = [
            'valor_pago'      => $novoValorPago,
            'situacao'        => $situacao,
            'forma_pagamento' => $request->forma_pagamento ?: $contaPagar->forma_pagamento,
        ];

        if ($situacao === 'paga') {
            $dados['data_pagamento'] = $request->data_pagamento;
        }

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
            'observacoes'     => $request->observacoes,
        ];
    }

    protected function garantirEmpresa(ContaPagar $conta): void
    {
        if ((int) $conta->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
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
