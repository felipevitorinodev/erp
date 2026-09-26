<?php

namespace App\Services;

use App\Models\Comissao;
use App\Models\ContaPagar;
use App\Models\Funcionario;
use App\Repositories\ComissaoRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComissaoService
{
    public function __construct(
        protected ComissaoRepository $repository,
        protected CategoriaPadraoService $categoriaPadraoService
    ) {}

    public function index(Request $request)
    {
        $comissoes = $this->repository->index($request);
        $totais    = $this->repository->totais($request);

        $funcionarios = Funcionario::where('empresa_id', auth()->user()->empresa_id)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('comissao.index', [
            'comissoes'    => $comissoes,
            'totais'       => $totais,
            'funcionarios' => $funcionarios,
            'filtros'      => $request->only(['funcionario_id', 'situacao', 'data_inicio', 'data_fim']),
        ]);
    }

    public function pagar(Comissao $comissao)
    {
        $this->garantirEmpresa($comissao);

        if ($comissao->situacao === 'paga') {
            return redirect()->back()
                ->with('error', 'Esta comissão já está paga.');
        }

        try {
            DB::transaction(function () use ($comissao) {
                $comissao = Comissao::where('id', $comissao->id)->lockForUpdate()->firstOrFail();

                if ($comissao->situacao === 'paga') {
                    throw new \Exception('Esta comissão já está paga.');
                }

                $comissao->load(['funcionario', 'venda']);
                $this->repository->marcarPaga($comissao);
                $this->gerarContaPagarDaComissao($comissao);
            });
        } catch (\Exception $ex) {
            return redirect()->back()->with('error', $ex->getMessage());
        }

        return redirect()->back()
            ->with('success', 'Comissão marcada como paga e conta a pagar registrada.');
    }

    protected function gerarContaPagarDaComissao(Comissao $comissao): void
    {
        $jaExiste = ContaPagar::where('empresa_id', $comissao->empresa_id)
            ->where('observacoes', 'comissao_id:' . $comissao->id)
            ->exists();

        if ($jaExiste) {
            return;
        }

        $hoje = now()->toDateString();
        $vendedor = $comissao->funcionario->nome ?? 'Vendedor';
        $vendaNumero = $comissao->venda->numero ?? $comissao->venda_id;

        ContaPagar::create([
            'empresa_id'      => $comissao->empresa_id,
            'descricao'       => 'Comissão venda #' . $vendaNumero . ' — ' . $vendedor,
            'valor'           => $comissao->valor,
            'valor_pago'      => $comissao->valor,
            'data_vencimento' => $hoje,
            'data_pagamento'  => $hoje,
            'categoria_id'    => $this->categoriaPadraoService->idDespesaComissoes((int) $comissao->empresa_id),
            'situacao'        => 'paga',
            'observacoes'     => 'comissao_id:' . $comissao->id,
        ]);
    }

    protected function garantirEmpresa(Comissao $comissao): void
    {
        if ((int) $comissao->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }
}
