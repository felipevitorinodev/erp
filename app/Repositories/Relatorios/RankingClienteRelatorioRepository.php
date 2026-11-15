<?php

namespace App\Repositories\Relatorios;

use App\Models\Vendas\Venda;
use App\Relatorios\Periodo;
use App\Support\IndiceBanco;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RankingClienteRelatorioRepository
{
    public function consulta(int $empresaId, Periodo $periodo): Builder
    {
        return IndiceBanco::usar(Venda::query(), 'vendas', 'vendas_relatorio_index')
            ->where('empresa_id', $empresaId)
            ->where('situacao', 'confirmada')
            ->whereDataEntre('data_venda', $periodo->inicio, $periodo->fim);
    }

    /**
     * Totais por cliente já com o nome, numa ida ao banco:
     *   SELECT t.*, c.nome FROM (SELECT cliente_id, COUNT(*), SUM(total) ... GROUP BY cliente_id) t
     *   LEFT JOIN clientes c ON c.id = t.cliente_id
     * A agregação roda primeiro (tabela derivada, o mesmo que uma CTE) e o join busca só um nome por
     * cliente agregado; antes os nomes vinham numa segunda consulta com WHERE id IN (dezenas de milhares de ids).
     * Clientes excluídos entram (o nome aparece no histórico), como antes.
     *
     * @return list<array{cliente_id: ?int, nome: ?string, qtd: int, valor: float}>
     */
    public function linhas(Builder $query, int $empresaId): array
    {
        $agregado = (clone $query)->reorder()->toBase();
        $agregado->columns = [];
        $agregado->select('cliente_id')
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw('COALESCE(SUM(total), 0) as valor')
            ->groupBy('cliente_id');

        return DB::query()
            ->fromSub($agregado, 't')
            ->leftJoin('clientes as c', function ($join) use ($empresaId) {
                $join->on('c.id', '=', 't.cliente_id')->where('c.empresa_id', '=', $empresaId);
            })
            ->select('t.cliente_id', 't.qtd', 't.valor', 'c.nome')
            ->get()
            ->map(fn ($linha) => [
                'cliente_id' => $linha->cliente_id ? (int) $linha->cliente_id : null,
                'nome' => $linha->nome !== null ? (string) $linha->nome : null,
                'qtd' => (int) $linha->qtd,
                'valor' => round((float) $linha->valor, 2),
            ])
            ->all();
    }

    /**
     * Faturamento das confirmadas no período: um SUM só (antes agrupava por cliente e somava no PHP).
     */
    public function faturamento(int $empresaId, Periodo $periodo): float
    {
        return round((float) $this->consulta($empresaId, $periodo)->sum('total'), 2);
    }
}
