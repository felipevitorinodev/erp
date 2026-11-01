<?php

namespace App\Repositories\Relatorios;

use App\Models\Produtos\Produto;
use Illuminate\Database\Eloquent\Builder;

class EstoqueRelatorioRepository
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consulta(int $empresaId, array $filtros): Builder
    {
        $query = Produto::query()
            ->where('produtos.empresa_id', $empresaId)
            ->where('produtos.ativo', true)
            ->where('produtos.controla_estoque', true);

        if (! empty($filtros['grupo_id'])) {
            $query->where('produtos.grupo_id', $filtros['grupo_id']);
        }

        if (! empty($filtros['apenas_critico'])) {
            $query->whereColumn('produtos.estoque_atual', '<=', 'produtos.estoque_minimo');
        }

        $estoque = $filtros['estoque'] ?? '';
        if ($estoque === 'zerado') {
            $query->where('produtos.estoque_atual', '<=', 0);
        } elseif ($estoque === 'com_estoque') {
            $query->where('produtos.estoque_atual', '>', 0);
        }

        if (($filtros['busca'] ?? '') !== '') {
            $busca = $filtros['busca'];
            $query->where(function ($q) use ($busca) {
                $q->where('produtos.nome', 'like', "%{$busca}%")
                    ->orWhere('produtos.codigo', 'like', "%{$busca}%");
            });
        }

        return $query;
    }

    /**
     * @return array{produtos: int, criticos: int, zerados: int, valor_custo: float}
     */
    public function totais(Builder $query): array
    {
        $linha = (clone $query)->toBase()->reorder()
            ->selectRaw('COUNT(*) as produtos')
            ->selectRaw('SUM(CASE WHEN estoque_atual <= estoque_minimo THEN 1 ELSE 0 END) as criticos')
            ->selectRaw('SUM(CASE WHEN estoque_atual <= 0 THEN 1 ELSE 0 END) as zerados')
            ->selectRaw('COALESCE(SUM(CASE WHEN estoque_atual > 0 THEN estoque_atual * COALESCE(preco_custo, 0) ELSE 0 END), 0) as valor_custo')
            ->first();

        return [
            'produtos' => (int) ($linha->produtos ?? 0),
            'criticos' => (int) ($linha->criticos ?? 0),
            'zerados' => (int) ($linha->zerados ?? 0),
            'valor_custo' => round((float) ($linha->valor_custo ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function ordenar(Builder $query, array $filtros): Builder
    {
        $colunas = [
            'codigo' => 'produtos.codigo',
            'nome' => 'produtos.nome',
            'minimo' => 'produtos.estoque_minimo',
            'atual' => 'produtos.estoque_atual',
        ];

        $ordem = $filtros['ordem'] ?? '';
        if ($ordem !== '' && isset($colunas[$ordem])) {
            $direcao = ($filtros['direcao'] ?? '') === 'desc' ? 'desc' : 'asc';

            return $query->orderBy($colunas[$ordem], $direcao)->orderBy('produtos.id');
        }

        return $query->orderBy('produtos.nome')->orderBy('produtos.id');
    }

    /**
     * @return list<array{nome: string, total: float}>
     */
    public function porGrupo(Builder $query): array
    {
        $consulta = (clone $query)->toBase()->reorder();
        $consulta->columns = [];

        return $consulta
            ->leftJoin('grupos', 'grupos.id', '=', 'produtos.grupo_id')
            ->selectRaw("COALESCE(grupos.nome, 'Sem grupo') as nome")
            ->selectRaw('COALESCE(SUM(CASE WHEN produtos.estoque_atual > 0 THEN produtos.estoque_atual * COALESCE(produtos.preco_custo, 0) ELSE 0 END), 0) as total')
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
     * @param  array{produtos: int, criticos: int, zerados: int, valor_custo: float}  $totais
     * @return list<array{nome: string, total: float}>
     */
    public function situacao(array $totais): array
    {
        $zerados = $totais['zerados'];
        $criticosComSaldo = max(0, $totais['criticos'] - $zerados);
        $regulares = max(0, $totais['produtos'] - $totais['criticos']);

        return [
            ['nome' => 'Regular', 'total' => (float) $regulares],
            ['nome' => 'Crítico com saldo', 'total' => (float) $criticosComSaldo],
            ['nome' => 'Sem estoque', 'total' => (float) $zerados],
        ];
    }
}
