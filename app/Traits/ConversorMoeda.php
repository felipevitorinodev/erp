<?php

namespace App\Traits;

trait ConversorMoeda
{
    protected function parseMoeda(mixed $valor): float
    {
        return $this->parseDecimal($valor, 2);
    }

    protected function parseQuantidade(mixed $valor): float
    {
        return $this->parseDecimal($valor, 2);
    }

    protected function parseDecimal(mixed $valor, int $casas = 2): float
    {
        if (is_null($valor) || $valor === '') {
            return 0.0;
        }

        $str = trim((string) $valor);

        if (str_contains($str, ',') && str_contains($str, '.')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }

        return round((float) $str, $casas);
    }
}
