<?php

namespace App\Traits;

use Illuminate\Validation\ValidationException;

trait ConversorMoeda
{
    protected function parseMoeda(mixed $valor): float
    {
        return $this->parseDecimal($valor, 2);
    }

    protected function parseQuantidade(mixed $valor): float
    {
        return $this->parseDecimal($valor, 3);
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

    protected function totalComDesconto(float $quantidade, float $preco, float $desconto, string $nome): float
    {
        $bruto = round($quantidade * $preco, 2);

        if (round($desconto, 2) > $bruto) {
            throw ValidationException::withMessages([
                'itens' => 'O desconto do item "'.$nome.'" não pode ser maior que o valor do item.',
            ]);
        }

        return round($bruto - $desconto, 2);
    }
}
