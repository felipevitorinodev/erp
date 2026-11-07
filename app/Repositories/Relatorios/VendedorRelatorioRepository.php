<?php

namespace App\Repositories\Relatorios;

use App\Models\Vendas\Venda;
use App\Relatorios\Periodo;
use Illuminate\Database\Eloquent\Builder;

class VendedorRelatorioRepository
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consulta(int $empresaId, Periodo $periodo, array $filtros): Builder
    {
        $query = Venda::query()
            ->where('empresa_id', $empresaId)
            ->whereDataEntre('data_venda', $periodo->inicio, $periodo->fim);

        if (($filtros['situacao'] ?? '') !== '') {
            $query->where('situacao', $filtros['situacao']);
        }

        if (! empty($filtros['funcionario_id'])) {
            $query->where('funcionario_id', $filtros['funcionario_id']);
        }

        return $query;
    }

    /**
     * @return list<array{funcionario_id: ?int, qtd_confirmadas: int, faturamento: float, qtd_andamento: int, qtd_canceladas: int}>
     */
    public function linhas(Builder $query): array
    {
        $consulta = (clone $query)->reorder()->toBase();
        $consulta->columns = [];

        return $consulta
            ->select('funcionario_id')
            ->selectRaw("SUM(CASE WHEN situacao = 'confirmada' THEN 1 ELSE 0 END) as qtd_confirmadas")
            ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'confirmada' THEN total END), 0) as faturamento")
            ->selectRaw("SUM(CASE WHEN situacao = 'em_andamento' THEN 1 ELSE 0 END) as qtd_andamento")
            ->selectRaw("SUM(CASE WHEN situacao = 'cancelada' THEN 1 ELSE 0 END) as qtd_canceladas")
            ->groupBy('funcionario_id')
            ->get()
            ->map(fn ($linha) => [
                'funcionario_id' => $linha->funcionario_id ? (int) $linha->funcionario_id : null,
                'qtd_confirmadas' => (int) $linha->qtd_confirmadas,
                'faturamento' => round((float) $linha->faturamento, 2),
                'qtd_andamento' => (int) $linha->qtd_andamento,
                'qtd_canceladas' => (int) $linha->qtd_canceladas,
            ])
            ->all();
    }
}
