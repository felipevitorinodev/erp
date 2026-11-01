<?php

namespace App\Repositories\Relatorios;

use App\Models\ContasReceber\ContaReceber;
use Illuminate\Database\Eloquent\Builder;

class ClienteDebitoRelatorioRepository
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consulta(int $empresaId, array $filtros): Builder
    {
        $query = ContaReceber::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('situacao', ['aberta', 'parcial']);

        if (! empty($filtros['cliente_id'])) {
            $query->where('cliente_id', $filtros['cliente_id']);
        }

        return $query;
    }

    /**
     * @return list<array{cliente_id: ?int, qtd: int, saldo: float, vencido: float}>
     */
    public function linhas(Builder $query): array
    {
        $hoje = now()->toDateString();
        $consulta = (clone $query)->reorder()->toBase();
        $consulta->columns = [];

        return $consulta
            ->select('cliente_id')
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw('COALESCE(SUM(valor - valor_pago), 0) as saldo')
            ->selectRaw('COALESCE(SUM(CASE WHEN data_vencimento < ? THEN valor - valor_pago ELSE 0 END), 0) as vencido', [$hoje])
            ->groupBy('cliente_id')
            ->havingRaw('COALESCE(SUM(valor - valor_pago), 0) > 0')
            ->get()
            ->map(fn ($linha) => [
                'cliente_id' => $linha->cliente_id ? (int) $linha->cliente_id : null,
                'qtd' => (int) $linha->qtd,
                'saldo' => round((float) $linha->saldo, 2),
                'vencido' => round((float) $linha->vencido, 2),
            ])
            ->all();
    }
}
