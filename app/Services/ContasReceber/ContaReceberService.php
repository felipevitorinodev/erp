<?php

namespace App\Services\ContasReceber;

use App\Http\Requests\ContasReceber\ContaReceberRequest;
use App\Impressao\CupomDocumento;
use App\Impressao\CupomPdf;
use App\Models\ContasReceber\ContaReceber;
use App\Repositories\CategoriasFinanceiras\CategoriaFinanceiraRepository;
use App\Repositories\ContasReceber\ContaReceberRepository;
use App\Support\Auditoria;
use App\Support\MensagemPublica;
use App\Traits\ConversorMoeda;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContaReceberService
{
    use ConversorMoeda;

    public function __construct(
        protected ContaReceberRepository $repository,
        protected CategoriaFinanceiraRepository $categoriaFinanceiraRepository
    ) {}

    public function index(Request $request)
    {
        $contas = $this->repository->index($request);

        return view('conta-receber.index', [
            'contas' => $contas,
            'filtros' => $request->only(['busca', 'situacao', 'data_vencimento']),
        ]);
    }

    public function create()
    {
        return view('conta-receber.create', [
            'categorias' => $this->categoriaFinanceiraRepository->listarAtivasPorTipo('receita'),
        ]);
    }

    public function store(ContaReceberRequest $request)
    {
        $conta = $this->lancar($request);

        return redirect()->route('conta-receber.show', $conta)
            ->with('success', 'Conta a receber cadastrada com sucesso.');
    }

    /**
     * Lança a conta já validada. A tela e os comandos usam o mesmo caminho.
     *
     * @throws ValidationException quando a venda informada já tem conta a receber
     */
    public function lancar(ContaReceberRequest $request): ContaReceber
    {
        $dados = $this->montarDados($request);

        if ($dados['venda_id'] && ContaReceber::where('venda_id', $dados['venda_id'])->where('situacao', '!=', 'cancelada')->exists()) {
            throw ValidationException::withMessages(['venda_id' => 'Esta venda já possui conta a receber.']);
        }

        $dados['empresa_id'] = auth()->user()->empresa_id;
        $dados['valor_pago'] = 0;
        $dados['situacao'] = 'aberta';

        return $this->repository->store($dados);
    }

    public function show(ContaReceber $contaReceber)
    {
        $this->garantirEmpresa($contaReceber);
        $contaReceber->load(['cliente', 'venda', 'categoria', 'baixas']);

        return view('conta-receber.show', ['conta' => $contaReceber]);
    }

    public function recibo(ContaReceber $contaReceber)
    {
        $this->garantirEmpresa($contaReceber);
        $contaReceber->load(['cliente', 'baixas', 'venda']);

        return CupomPdf::stream(CupomDocumento::reciboConta($contaReceber), 'recibo_conta_'.$contaReceber->id.'.pdf');
    }

    public function edit(ContaReceber $contaReceber)
    {
        $this->garantirEmpresa($contaReceber);

        if (in_array($contaReceber->situacao, ['paga', 'cancelada'], true)) {
            return redirect()->route('conta-receber.show', $contaReceber)
                ->with('error', 'Conta paga ou cancelada não pode ser editada.');
        }

        $contaReceber->load(['cliente', 'venda', 'categoria']);

        $categorias = $this->categoriaFinanceiraRepository->listarAtivasPorTipo('receita');
        if ($contaReceber->categoria_id && ! $categorias->contains('id', $contaReceber->categoria_id) && $contaReceber->categoria) {
            $categorias->push($contaReceber->categoria);
            $categorias = $categorias->sortBy('nome')->values();
        }

        return view('conta-receber.edit', [
            'conta' => $contaReceber,
            'categorias' => $categorias,
        ]);
    }

    public function update(ContaReceberRequest $request, ContaReceber $contaReceber)
    {
        $this->garantirEmpresa($contaReceber);

        if (in_array($contaReceber->situacao, ['paga', 'cancelada'], true)) {
            return redirect()->route('conta-receber.show', $contaReceber)
                ->with('error', 'Conta paga ou cancelada não pode ser editada.');
        }

        $dados = $this->montarDados($request);
        // O vínculo com a venda é definido na criação; editar a conta não pode desfazê-lo.
        unset($dados['venda_id']);

        try {
            $this->repository->update($contaReceber, $dados);
        } catch (\RuntimeException $ex) {
            return back()
                ->withErrors(['valor' => MensagemPublica::de($ex)])
                ->withInput();
        }

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
        $saldo = (float) $contaReceber->valor - (float) $contaReceber->valor_pago;

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

        if (! $request->filled('data_pagamento')) {
            return back()
                ->withErrors(['data_pagamento' => 'A data de pagamento é obrigatória.'])
                ->withInput();
        }

        $request->validate([
            'data_pagamento' => 'required|date|before_or_equal:today',
            'forma_pagamento' => 'nullable|string|max:100',
        ], [
            'data_pagamento.date' => 'Informe uma data de pagamento válida.',
            'data_pagamento.before_or_equal' => 'A data de pagamento não pode ser futura.',
        ]);

        try {
            $this->repository->receber(
                $contaReceber,
                $valorInformado,
                $request->data_pagamento,
                $request->forma_pagamento ?: $contaReceber->forma_pagamento
            );
        } catch (\RuntimeException $ex) {
            return back()
                ->withErrors(['valor_pago' => MensagemPublica::de($ex)])
                ->withInput();
        }

        Auditoria::registrar('conta_receber.recebimento', 'conta_receber', $contaReceber->id, [
            'valor' => $valorInformado,
            'data_pagamento' => $request->data_pagamento,
            'saldo_anterior' => round($saldo, 2),
        ]);

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

        if (in_array($contaReceber->situacao, ['paga', 'parcial'], true)) {
            return redirect()->route('conta-receber.show', $contaReceber)
                ->with('error', 'Conta com recebimento não pode ser cancelada.');
        }

        try {
            $this->repository->cancelar($contaReceber);
        } catch (\RuntimeException $ex) {
            return redirect()->route('conta-receber.show', $contaReceber)
                ->with('error', MensagemPublica::de($ex));
        }

        Auditoria::registrar('conta_receber.cancelada', 'conta_receber', $contaReceber->id, [
            'valor' => (float) $contaReceber->valor,
            'venda_id' => $contaReceber->venda_id,
        ]);

        return redirect()->route('conta-receber.show', $contaReceber)
            ->with('success', 'Conta a receber cancelada.');
    }

    public function destroy(ContaReceber $contaReceber)
    {
        $this->garantirEmpresa($contaReceber);

        if ($contaReceber->venda_id) {
            return redirect()->route('conta-receber.show', $contaReceber)
                ->with('error', 'Conta vinculada a uma venda não pode ser excluída. Cancele a venda ou a conta.');
        }

        if (in_array($contaReceber->situacao, ['paga', 'parcial'], true)) {
            return redirect()->route('conta-receber.show', $contaReceber)
                ->with('error', 'Conta com recebimento registrado não pode ser excluída. Cancele-a primeiro, se permitido.');
        }

        $this->repository->destroy($contaReceber->id);

        Auditoria::registrar('conta_receber.excluida', 'conta_receber', $contaReceber->id, [
            'valor' => (float) $contaReceber->valor,
            'situacao' => $contaReceber->situacao,
        ]);

        return redirect()->route('conta-receber.index')
            ->with('success', 'Conta a receber excluída com sucesso.');
    }

    protected function montarDados(ContaReceberRequest $request): array
    {
        return [
            'cliente_id' => $request->cliente_id ?: null,
            'venda_id' => $request->venda_id ?: null,
            'descricao' => $request->descricao,
            'valor' => $this->parseMoeda($request->valor),
            'data_vencimento' => $request->data_vencimento,
            'forma_pagamento' => $request->forma_pagamento,
            'categoria_id' => $request->categoria_id ?: null,
            'observacoes' => $request->observacoes,
        ];
    }

    protected function garantirEmpresa(ContaReceber $conta): void
    {
        if ((int) $conta->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }
}
