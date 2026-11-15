<?php

namespace App\Repositories\Relatorios;

use App\Relatorios\Periodo;
use App\Support\IndiceBanco;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProdutoMaisVendidoRelatorioRepository
{
    private bool $memoriaAjustada = false;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consulta(int $empresaId, Periodo $periodo, array $filtros): Builder
    {
        $this->agruparEmMemoria();

        // Parte das vendas do período (índice de relatório) e junta os itens pelo índice que já traz nome,
        // código, quantidade e total: a soma não lê nenhuma linha das tabelas.
        $query = IndiceBanco::usar(DB::table('vendas'), 'vendas', 'vendas_relatorio_index')
            ->join('venda_itens', 'venda_itens.venda_id', '=', 'vendas.id')
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
     * Página do ranking e totais gerais numa consulta só: o GROUP BY roda uma vez numa tabela derivada
     * (equivalente a uma CTE) e as funções de janela COUNT(*) OVER () / SUM(...) OVER () levam os totais
     * de todos os produtos em cada linha da página. Antes eram duas agregações completas (totais + página).
     *
     * @param  array<string, mixed>  $filtros
     * @return array{linhas: Collection<int, object>, totais: ?array{produtos: int, qtd_total: float, valor_total: float}}
     */
    public function paginaComTotais(Builder $query, array $filtros, int $porPagina, int $pagina): array
    {
        $ordenar = ($filtros['ordenar'] ?? '') === 'valor' ? 'valor_total' : 'qtd_total';

        $linhas = DB::query()
            ->fromSub((clone $query)->reorder(), 'mais_vendidos')
            ->select('mais_vendidos.*')
            ->selectRaw('COUNT(*) OVER () as janela_produtos')
            ->selectRaw('SUM(qtd_total) OVER () as janela_qtd')
            ->selectRaw('SUM(valor_total) OVER () as janela_valor')
            ->orderByDesc($ordenar)
            ->orderBy('produto_nome')
            ->orderBy('produto_codigo')
            ->offset(max(0, ($pagina - 1) * $porPagina))
            ->limit($porPagina)
            ->get();

        $primeira = $linhas->first();

        return [
            'linhas' => $linhas,
            // Página além da última não traz linha (nem total): quem chama faz a contagem à parte.
            'totais' => $primeira === null ? null : [
                'produtos' => (int) $primeira->janela_produtos,
                'qtd_total' => round((float) $primeira->janela_qtd, 3),
                'valor_total' => round((float) $primeira->janela_valor, 2),
            ],
        ];
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
        $query = IndiceBanco::usar(DB::table('vendas'), 'vendas', 'vendas_relatorio_index')
            ->join('venda_itens', 'venda_itens.venda_id', '=', 'vendas.id')
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

    /**
     * O GROUP BY por nome + código do produto cria uma tabela temporária com dezenas de milhares de grupos.
     * Com o limite padrão do MySQL (tmp_table_size = 16 MB) ela ia para o disco: 12 s num ano com 50 mil
     * produtos; em memória, 3 s. Vale só para esta conexão; o teto global (temptable_max_ram) continua valendo.
     */
    private function agruparEmMemoria(): void
    {
        $conexao = DB::connection();
        if ($this->memoriaAjustada || ! in_array($conexao->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $conexao->statement('SET SESSION tmp_table_size = 268435456');
        $this->memoriaAjustada = true;
    }
}
