<?php

namespace App\Repositories\Relatorios;

use App\Models\Vendas\Venda;
use App\Relatorios\Periodo;
use Illuminate\Database\Eloquent\Builder;

class RankingClienteRelatorioRepository
{
    public function consulta(int $empresaId, Periodo $periodo): Builder
    {
        return Venda::query()
            ->where('empresa_id', $empresaId)
            ->where('situacao', 'confirmada')
            ->whereDataEntre('data_venda', $periodo->inicio, $periodo->fim);
    }

    /**
     * @return list<array{cliente_id: ?int, qtd: int, valor: float}>
     */
    public function linhas(Builder $query): array
    {
        $consulta = (clone $query)->reorder()->toBase();
        $consulta->columns = [];

        return $consulta
            ->select('cliente_id')
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw('COALESCE(SUM(total), 0) as valor')
            ->groupBy('cliente_id')
            ->get()
            ->map(fn ($linha) => [
                'cliente_id' => $linha->cliente_id ? (int) $linha->cliente_id : null,
                'qtd' => (int) $linha->qtd,
                'valor' => round((float) $linha->valor, 2),
            ])
            ->all();
    }
}
