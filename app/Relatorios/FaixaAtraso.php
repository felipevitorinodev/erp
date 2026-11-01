<?php

namespace App\Relatorios;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FaixaAtraso
{
    public const FAIXAS = ['1-30', '31-60', '61-90', '91+'];

    public static function nome(int $dias): string
    {
        return match (true) {
            $dias <= 30 => '1-30',
            $dias <= 60 => '31-60',
            $dias <= 90 => '61-90',
            default => '91+',
        };
    }

    public static function rotulo(string $faixa): string
    {
        return match ($faixa) {
            '1-30' => '1–30 dias',
            '31-60' => '31–60 dias',
            '61-90' => '61–90 dias',
            '91+' => '91+ dias',
            default => $faixa,
        };
    }

    public static function dias(Carbon|string $vencimento): int
    {
        $data = $vencimento instanceof Carbon
            ? $vencimento->copy()->startOfDay()
            : Carbon::parse($vencimento)->startOfDay();

        return $data->diff(now()->startOfDay())->days;
    }

    public static function expressao(string $coluna): string
    {
        $dias = self::sqlDias($coluna);

        return "CASE WHEN {$dias} BETWEEN 1 AND 30 THEN '1-30'"
            ." WHEN {$dias} BETWEEN 31 AND 60 THEN '31-60'"
            ." WHEN {$dias} BETWEEN 61 AND 90 THEN '61-90'"
            ." ELSE '91+' END";
    }

    /**
     * @return list<string>
     */
    public static function ligacoes(string $hoje): array
    {
        return [$hoje, $hoje, $hoje];
    }

    public static function filtro(string $coluna, string $faixa): ?string
    {
        $dias = self::sqlDias($coluna);

        return match ($faixa) {
            '1-30' => "{$dias} BETWEEN 1 AND 30",
            '31-60' => "{$dias} BETWEEN 31 AND 60",
            '61-90' => "{$dias} BETWEEN 61 AND 90",
            '91+' => "{$dias} >= 91",
            default => null,
        };
    }

    private static function sqlDias(string $coluna): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(julianday(?) - julianday({$coluna}) AS INTEGER)"
            : "DATEDIFF(?, {$coluna})";
    }
}
