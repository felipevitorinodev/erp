<?php

namespace App\Repositories\Relatorios;

use App\Models\Vendas\Venda;
use App\Relatorios\Periodo;
use App\Relatorios\SerieTemporal;
use Illuminate\Database\Eloquent\Builder;

class VendaRelatorioRepository
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

        if (($filtros['forma_pagamento'] ?? '') !== '') {
            $query->where('forma_pagamento', 'like', '%'.$filtros['forma_pagamento'].'%');
        }

        if (($filtros['busca'] ?? '') !== '') {
            $busca = $filtros['busca'];
            $query->where(function ($q) use ($busca) {
                $q->where('numero', 'like', "%{$busca}%")
                    ->orWhereHas('cliente', fn ($cliente) => $cliente->where('nome', 'like', "%{$busca}%"));
            });
        }

        if (! empty($filtros['cliente_id'])) {
            $query->where('cliente_id', $filtros['cliente_id']);
        }

        if (! empty($filtros['funcionario_id'])) {
            $query->where('funcionario_id', $filtros['funcionario_id']);
        }

        return $query;
    }

    /**
     * @return array<string, int|float>
     */
    public function totais(Builder $query): array
    {
        $linha = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as registros')
            ->selectRaw("SUM(CASE WHEN situacao = 'confirmada' THEN 1 ELSE 0 END) as qtd_confirmadas")
            ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'confirmada' THEN total END), 0) as valor_confirmadas")
            ->selectRaw("SUM(CASE WHEN situacao = 'em_andamento' THEN 1 ELSE 0 END) as qtd_em_andamento")
            ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'em_andamento' THEN total END), 0) as valor_em_andamento")
            ->selectRaw("SUM(CASE WHEN situacao = 'cancelada' THEN 1 ELSE 0 END) as qtd_canceladas")
            ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'cancelada' THEN total END), 0) as valor_canceladas")
            ->first();

        $qtdConfirmadas = (int) ($linha->qtd_confirmadas ?? 0);
        $valorConfirmadas = round((float) ($linha->valor_confirmadas ?? 0), 2);

        return [
            'registros' => (int) ($linha->registros ?? 0),
            'qtd_confirmadas' => $qtdConfirmadas,
            'valor_confirmadas' => $valorConfirmadas,
            'ticket_medio' => $qtdConfirmadas > 0 ? round($valorConfirmadas / $qtdConfirmadas, 2) : 0.0,
            'qtd_em_andamento' => (int) ($linha->qtd_em_andamento ?? 0),
            'valor_em_andamento' => round((float) ($linha->valor_em_andamento ?? 0), 2),
            'qtd_canceladas' => (int) ($linha->qtd_canceladas ?? 0),
            'valor_canceladas' => round((float) ($linha->valor_canceladas ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function ordenar(Builder $query, array $filtros): Builder
    {
        $colunas = [
            'data' => 'data_venda',
            'numero' => 'numero',
            'total' => 'total',
            'situacao' => 'situacao',
        ];

        $ordem = $filtros['ordem'] ?? '';
        if ($ordem !== '' && isset($colunas[$ordem])) {
            $direcao = ($filtros['direcao'] ?? '') === 'desc' ? 'desc' : 'asc';

            return $query->orderBy($colunas[$ordem], $direcao)->orderBy('id');
        }

        return $query->orderBy('data_venda')->orderBy('numero')->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, float>
     */
    public function serie(Builder $query, Periodo $periodo, array $filtros): array
    {
        $serie = clone $query;
        if (($filtros['situacao'] ?? '') === '') {
            $serie->where('situacao', 'confirmada');
        }

        $consulta = $serie->toBase();
        $consulta->columns = [];

        if ($periodo->porMes()) {
            $chave = SerieTemporal::chaveMes('data_venda');
            $linhas = $consulta
                ->selectRaw($chave.' as chave')
                ->selectRaw('COALESCE(SUM(total), 0) as total')
                ->groupByRaw($chave)
                ->get();
            $tamanho = 7;
        } else {
            $linhas = $consulta
                ->selectRaw('data_venda as chave')
                ->selectRaw('COALESCE(SUM(total), 0) as total')
                ->groupBy('data_venda')
                ->get();
            $tamanho = 10;
        }

        $mapa = [];
        foreach ($linhas as $linha) {
            $mapa[substr((string) $linha->chave, 0, $tamanho)] = (float) $linha->total;
        }

        return $mapa;
    }

    /**
     * @return list<array{nome: string, total: float}>
     */
    public function formas(Builder $query, array $filtros): array
    {
        $formas = clone $query;
        if (($filtros['situacao'] ?? '') === '') {
            $formas->where('situacao', 'confirmada');
        }

        $consulta = $formas->toBase();
        $consulta->columns = [];

        return $consulta
            ->selectRaw("COALESCE(NULLIF(forma_pagamento, ''), 'Não informada') as nome")
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->groupByRaw("COALESCE(NULLIF(forma_pagamento, ''), 'Não informada')")
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
