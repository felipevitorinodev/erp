<?php

namespace App\Relatorios;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SerieTemporal
{
    /**
     * @param  array<string, float|int>  $valores
     * @return array{labels: list<string>, dados: list<float>, vazio: bool}
     */
    public static function preencher(Periodo $periodo, array $valores): array
    {
        $inicio = Carbon::parse($periodo->inicio)->startOfDay();
        $fim = Carbon::parse($periodo->fim)->startOfDay();
        $labels = [];
        $dados = [];

        if ($periodo->porMes()) {
            $cursor = $inicio->copy()->startOfMonth();
            $limite = $fim->copy()->startOfMonth();

            while ($cursor->lte($limite)) {
                $labels[] = $cursor->format('m/Y');
                $dados[] = round((float) ($valores[$cursor->format('Y-m')] ?? 0), 2);
                $cursor->addMonth();
            }
        } else {
            $cursor = $inicio->copy();

            while ($cursor->lte($fim)) {
                $labels[] = $cursor->format('d/m');
                $dados[] = round((float) ($valores[$cursor->toDateString()] ?? 0), 2);
                $cursor->addDay();
            }
        }

        return [
            'labels' => $labels,
            'dados' => $dados,
            'vazio' => array_sum($dados) == 0.0,
        ];
    }

    /**
     * Agrupa por mês sem depender de uma função exclusiva do MySQL.
     */
    public static function chaveMes(string $coluna): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$coluna})"
            : "DATE_FORMAT({$coluna}, '%Y-%m')";
    }

    /**
     * Soma uma expressão por dia ou por mês, na mesma chave que preencher() espera.
     *
     * @return array<string, float>
     */
    public static function mapa(Builder $query, Periodo $periodo, string $colunaData, string $expressaoValor): array
    {
        $consulta = (clone $query)->reorder()->toBase();
        $consulta->columns = [];

        if ($periodo->porMes()) {
            $chave = self::chaveMes($colunaData);
            $linhas = $consulta
                ->selectRaw($chave.' as chave')
                ->selectRaw('COALESCE(SUM('.$expressaoValor.'), 0) as total')
                ->groupByRaw($chave)
                ->get();
            $tamanho = 7;
        } else {
            $linhas = $consulta
                ->selectRaw($colunaData.' as chave')
                ->selectRaw('COALESCE(SUM('.$expressaoValor.'), 0) as total')
                ->groupBy($colunaData)
                ->get();
            $tamanho = 10;
        }

        $mapa = [];
        foreach ($linhas as $linha) {
            $mapa[substr((string) $linha->chave, 0, $tamanho)] = round((float) $linha->total, 2);
        }

        return $mapa;
    }
}
