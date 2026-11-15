<?php

namespace App\Repositories\Comissoes;

use App\Models\Comissoes\Comissao;
use App\Support\CacheDados;
use App\Support\ContagemPaginada;
use App\Support\IndiceBanco;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ComissaoRepository
{
    /**
     * @param  int|null  $total  quantidade já contada (ex.: qtd dos totais), para não refazer o COUNT(*)
     */
    public function index(Request $request, ?int $total = null)
    {
        // select comissoes.*: com o filtro de período há JOIN com vendas, e o id/situacao da venda não podem
        // sobrescrever os da comissão.
        $query = $this->filtrar($request)
            ->select('comissoes.*')
            ->with([
                'funcionario:id,nome',
                'venda:id,numero,data_venda',
                'venda.ordemServico:id,venda_id,numero',
            ])
            ->orderByDesc('comissoes.id');

        if ($this->filtraPeriodo($request)) {
            // Página pelo índice (empresa, id): percorre das comissões mais novas para trás e para nas 20 do
            // período. Sem a dica o MySQL às vezes juntava as 330 mil vendas do ano antes de ordenar (até 2,9 s);
            // com ela, ano ou mês recentes saem em ~7 ms (períodos de anos atrás: ~1 s).
            IndiceBanco::usar($query, 'comissoes', 'comissoes_lista_index');
        }

        return $query->paginate(20, ['*'], 'page', null, $total ?? ContagemPaginada::total($query, ['vendas']));
    }

    public function totais(Request $request): array
    {
        $empresaId = (int) auth()->user()->empresa_id;
        $parametros = $request->only(['funcionario_id', 'situacao', 'data_inicio', 'data_fim']);

        // Mesmos totais a cada página da listagem: guardados até a próxima gravação de venda/comissão.
        // A quantidade sai na mesma leitura e serve de total da paginação (um COUNT(*) a menos).
        return CacheDados::lembrar('comissoes.totais', ['vendas'], $parametros, function () use ($request) {
            $somas = $this->filtrar($request, true)
                ->toBase()
                ->selectRaw('COUNT(*) as qtd')
                ->selectRaw("COALESCE(SUM(CASE WHEN comissoes.situacao = 'pendente' THEN comissoes.valor END), 0) as pendentes")
                ->selectRaw("COALESCE(SUM(CASE WHEN comissoes.situacao = 'paga' THEN comissoes.valor END), 0) as pagas")
                ->first();

            return [
                'qtd' => (int) $somas->qtd,
                'pendentes' => (float) $somas->pendentes,
                'pagas' => (float) $somas->pagas,
            ];
        }, 600, $empresaId);
    }

    public function marcarPaga(Comissao $comissao): Comissao
    {
        $comissao->update([
            'situacao' => 'paga',
            'data_pagamento' => now()->toDateString(),
        ]);

        return $comissao->fresh();
    }

    /**
     * @param  bool  $somarPeriodo  para os totais: as vendas do período são lidas pelo índice (empresa, data). Sem a
     *                              dica o MySQL lia todas as vendas da empresa por outro índice (ano: 2,7 s → 0,9 s;
     *                              mês: 40 ms nos dois). Na página não: lá a ordem vem do índice das comissões.
     */
    protected function filtrar(Request $request, bool $somarPeriodo = false): Builder
    {
        $empresaId = (int) auth()->user()->empresa_id;
        // Colunas qualificadas: o filtro de período junta a tabela de vendas, que também tem essas colunas.
        $query = Comissao::query()->where('comissoes.empresa_id', $empresaId);

        if ($request->filled('funcionario_id')) {
            $query->where('comissoes.funcionario_id', $request->funcionario_id);
        }

        if ($request->filled('situacao')) {
            $query->where('comissoes.situacao', $request->situacao);
        }

        if ($this->filtraPeriodo($request)) {
            // JOIN com a venda (cada comissão tem uma venda: não duplica linhas) em vez de EXISTS por comissão.
            // Medido num ano com 280 mil comissões, por consulta: IN (subconsulta) 1,2 s; JOIN 0,7 s.
            // A exclusão lógica da venda continua valendo, como no whereHas de antes.
            $vendas = $somarPeriodo
                ? IndiceBanco::tabela($query, 'vendas', 'venda_periodo', 'vendas_relatorio_index')
                : 'vendas as venda_periodo';
            $query->join($vendas, 'venda_periodo.id', '=', 'comissoes.venda_id')
                ->where('venda_periodo.empresa_id', $empresaId)
                ->whereNull('venda_periodo.deleted_at');
            if ($request->filled('data_inicio')) {
                $query->whereDataDe('venda_periodo.data_venda', $request->data_inicio);
            }
            if ($request->filled('data_fim')) {
                $query->whereDataAte('venda_periodo.data_venda', $request->data_fim);
            }
        }

        return $query;
    }

    private function filtraPeriodo(Request $request): bool
    {
        return $request->filled('data_inicio') || $request->filled('data_fim');
    }
}
