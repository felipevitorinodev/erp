<?php

namespace App\Repositories\Relatorios;

use App\Relatorios\Periodo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProdutoMaisVendidoRelatorioRepository
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consulta(int $empresaId, Periodo $periodo, array $filtros): Builder
    {
        $query = DB::table('venda_itens')
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->where('vendas.empresa_id', $empresaId)
            ->where('vendas.situacao', 'confirmada')
            ->whereNull('vendas.deleted_at')
            ->whereDataEntre('vendas.data_venda', $periodo->inicio, $periodo->fim);

        if (! empty($filtros['grupo_id'])) {
            $query->join('produtos', 'produtos.id', '=', 'venda_itens.produto_id')
                ->where('produtos.grupo_id', $filtros['grupo_id']);
        }

        if (($filtros['busca'] ?? '') !== '') {
            $busca = $filtros['busca'];
            $query->where(function ($q) use ($busca) {
                $q->where('venda_itens.produto_nome', 'like', "%{$busca}%")
                    ->orWhere('venda_itens.produto_codigo', 'like', "%{$busca}%");
            });
        }

        $ordenar = ($filtros['ordenar'] ?? '') === 'valor' ? 'valor_total' : 'qtd_total';

        return $query
            ->groupBy('venda_itens.produto_nome', 'venda_itens.produto_codigo')
            ->select(
                'venda_itens.produto_nome',
                'venda_itens.produto_codigo',
                DB::raw('SUM(venda_itens.quantidade) as qtd_total'),
                DB::raw('SUM(venda_itens.total) as valor_total')
            )
            ->orderByDesc($ordenar)
            ->orderBy('venda_itens.produto_nome')
            ->orderBy('venda_itens.produto_codigo');
    }

    /**
     * @return array{produtos: int, qtd_total: float, valor_total: float}
     */
    public function totais(Builder $query): array
    {
        $linha = DB::query()
            ->fromSub((clone $query)->reorder(), 'mais_vendidos')
            ->selectRaw('COUNT(*) as produtos')
            ->selectRaw('COALESCE(SUM(qtd_total), 0) as qtd_total')
            ->selectRaw('COALESCE(SUM(valor_total), 0) as valor_total')
            ->first();

        return [
            'produtos' => (int) ($linha->produtos ?? 0),
            'qtd_total' => round((float) ($linha->qtd_total ?? 0), 3),
            'valor_total' => round((float) ($linha->valor_total ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{nome: string, total: float}>
     */
    public function porGrupo(int $empresaId, Periodo $periodo, array $filtros): array
    {
        $query = DB::table('venda_itens')
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->leftJoin('produtos', 'produtos.id', '=', 'venda_itens.produto_id')
            ->leftJoin('grupos', 'grupos.id', '=', 'produtos.grupo_id')
            ->where('vendas.empresa_id', $empresaId)
            ->where('vendas.situacao', 'confirmada')
            ->whereNull('vendas.deleted_at')
            ->whereDataEntre('vendas.data_venda', $periodo->inicio, $periodo->fim);

        if (! empty($filtros['grupo_id'])) {
            $query->where('produtos.grupo_id', $filtros['grupo_id']);
        }

        if (($filtros['busca'] ?? '') !== '') {
            $busca = $filtros['busca'];
            $query->where(function ($q) use ($busca) {
                $q->where('venda_itens.produto_nome', 'like', "%{$busca}%")
                    ->orWhere('venda_itens.produto_codigo', 'like', "%{$busca}%");
            });
        }

        return $query
            ->selectRaw("COALESCE(grupos.nome, 'Sem grupo') as nome")
            ->selectRaw('COALESCE(SUM(venda_itens.total), 0) as total')
            ->groupByRaw("COALESCE(grupos.nome, 'Sem grupo')")
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($linha) => [
                'nome' => (string) $linha->nome,
                'total' => round((float) $linha->total, 2),
            ])
            ->all();
    }
}
