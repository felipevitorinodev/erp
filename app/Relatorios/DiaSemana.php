<?php

namespace App\Relatorios;

use Illuminate\Support\Facades\DB;

class DiaSemana
{
    /** Segunda a domingo. Zero é domingo, como no strftime %w e no DAYOFWEEK − 1. */
    public const ORDEM = [1, 2, 3, 4, 5, 6, 0];

    public static function nome(int $dia): string
    {
        return match ($dia) {
            1 => 'Segunda',
            2 => 'Terça',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sábado',
            0 => 'Domingo',
            default => '—',
        };
    }

    public static function curto(int $dia): string
    {
        return match ($dia) {
            1 => 'Seg',
            2 => 'Ter',
            3 => 'Qua',
            4 => 'Qui',
            5 => 'Sex',
            6 => 'Sáb',
            0 => 'Dom',
            default => '—',
        };
    }

    public static function expressao(string $coluna): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%w', {$coluna}) AS INTEGER)"
            : '(DAYOFWEEK('.$coluna.') - 1)';
    }
}
