<?php

namespace App\Repositories\Relatorios;

use App\Models\Vendas\Venda;
use App\Relatorios\DiaSemana;
use App\Relatorios\Periodo;
use Illuminate\Database\Eloquent\Builder;

class DiaSemanaRelatorioRepository
{
    public function consulta(int $empresaId, Periodo $periodo): Builder
    {
        return Venda::query()
            ->where('empresa_id', $empresaId)
            ->where('situacao', 'confirmada')
            ->whereDataEntre('data_venda', $periodo->inicio, $periodo->fim);
    }

    /**
     * @return array<int, array{qtd: int, valor: float}>
     */
    public function porDia(Builder $query): array
    {
        $expressao = DiaSemana::expressao('data_venda');
        $consulta = (clone $query)->reorder()->toBase();
        $consulta->columns = [];

        $mapa = [];
        foreach ($consulta
            ->selectRaw($expressao.' as dia')
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw('COALESCE(SUM(total), 0) as valor')
            ->groupByRaw($expressao)
            ->get() as $linha) {
            $mapa[(int) $linha->dia] = [
                'qtd' => (int) $linha->qtd,
                'valor' => round((float) $linha->valor, 2),
            ];
        }

        return $mapa;
    }
}
