<?php

namespace App\Services;

use App\Http\Requests\ContaReceberRequest;
use App\Models\ContaReceber;
use App\Repositories\ContaReceberRepository;
use Illuminate\Http\Request;

class ContaReceberService
{
    public function __construct(
        protected ContaReceberRepository $repository
    ) {}

    public function index(Request $request)
    {
        $contas = $this->repository->index($request);

        return view('conta-receber.index', [
            'contas'   => $contas,
            'filtros'  => $request->only(['situacao', 'data_vencimento']),
        ]);
    }

    public function create()
    {
        return view('conta-receber.create');
    }

    public function store(ContaReceberRequest $request)
    {
        $dados = $this->montarDados($request);
        $dados['empresa_id'] = auth()->user()->empresa_id;
        $dados['valor_pago'] = 0;
        $dados['situacao']   = 'aberta';

        $conta = $this->repository->store($dados);

        return redirect()->route('conta-receber.show', $conta)
            ->with('success', 'Conta a receber cadastrada com sucesso.');
    }

    public function show(ContaReceber $contaReceber)
    {
        $this->garantirEmpresa($contaReceber);
        $contaReceber->load(['cliente', 'venda']);

        return view('conta-receber.show', ['conta' => $contaReceber]);
    }

    public function edit(ContaReceber $contaReceber)
    {
        $this->garantirEmpresa($contaReceber);

        if (in_array($contaReceber->situacao, ['paga', 'cancelada'], true)) {
            return redirect()->route('conta-receber.show', $contaReceber)
                ->with('error', 'Conta paga ou cancelada não pode ser editada.');
        }

        $contaReceber->load(['cliente', 'venda']);

        return view('conta-receber.edit', ['conta' => $contaReceber]);
    }

    public function update(ContaReceberRequest $request, ContaReceber $contaReceber)
    {
        $this->garantirEmpresa($contaReceber);

        if (in_array($contaReceber->situacao, ['paga', 'cancelada'], true)) {
            return redirect()->route('conta-receber.show', $contaReceber)
                ->with('error', 'Conta paga ou cancelada não pode ser editada.');
        }

        $dados = $this->montarDados($request);
        $this->repository->update($contaReceber, $dados);

        return redirect()->route('conta-receber.show', $contaReceber)
            ->with('success', 'Conta a receber atualizada com sucesso.');
    }

    public function receber(Request $request, ContaReceber $contaReceber)
    {
        $this->garantirEmpresa($contaReceber);

        if (in_array($contaReceber->situacao, ['paga', 'cancelada'], true)) {
            return redirect()->route('conta-receber.show', $contaReceber)
                ->with('error', 'Não é possível receber nesta conta.');
        }

        $valorInformado = $this->parseMoeda($request->valor_pago);
        $saldo          = (float) $contaReceber->valor - (float) $contaReceber->valor_pago;

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

        $novoValorPago = round((float) $contaReceber->valor_pago + $valorInformado, 2);
        $situacao      = $novoValorPago >= (float) $contaReceber->valor ? 'paga' : 'parcial';

        $dados = [
            'valor_pago'      => $novoValorPago,
            'situacao'        => $situacao,
            'forma_pagamento' => $request->forma_pagamento ?: $contaReceber->forma_pagamento,
        ];

        if ($situacao === 'paga') {
            $dados['data_pagamento'] = $request->data_pagamento;
        }

        $this->repository->receber($contaReceber, $dados);

        return redirect()->route('conta-receber.show', $contaReceber)
            ->with('success', 'Recebimento registrado com sucesso.');
    }

    public function cancelar(ContaReceber $contaReceber)
    {
        $this->garantirEmpresa($contaReceber);

        if ($contaReceber->situacao === 'cancelada') {
            return redirect()->route('conta-receber.show', $contaReceber)
                ->with('error', 'Conta já está cancelada.');
        }

        if ($contaReceber->situacao === 'paga') {
            return redirect()->route('conta-receber.show', $contaReceber)
                ->with('error', 'Conta paga não pode ser cancelada.');
        }

        $this->repository->cancelar($contaReceber);

        return redirect()->route('conta-receber.show', $contaReceber)
            ->with('success', 'Conta a receber cancelada.');
    }

    public function destroy(ContaReceber $contaReceber)
    {
        $this->garantirEmpresa($contaReceber);
        $this->repository->destroy($contaReceber->id);

        return redirect()->route('conta-receber.index')
            ->with('success', 'Conta a receber excluída com sucesso.');
    }

    protected function montarDados(ContaReceberRequest $request): array
    {
        return [
            'cliente_id'      => $request->cliente_id ?: null,
            'venda_id'        => $request->venda_id ?: null,
            'descricao'       => $request->descricao,
            'valor'           => $this->parseMoeda($request->valor),
            'data_vencimento' => $request->data_vencimento,
            'forma_pagamento' => $request->forma_pagamento,
            'observacoes'     => $request->observacoes,
        ];
    }

    protected function garantirEmpresa(ContaReceber $conta): void
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
