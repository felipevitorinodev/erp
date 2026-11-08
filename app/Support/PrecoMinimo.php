<?php

namespace App\Support;

use App\Models\Produtos\Produto;
use Illuminate\Validation\ValidationException;

/**
 * Preço mínimo de venda do cadastro do produto (campo `preco_minimo`, quando maior que zero).
 *
 * O preço comparado é o líquido por unidade: já tira o desconto do item e a parte proporcional
 * do desconto geral. O acréscimo não entra na conta. Quem tem a permissão vende abaixo do mínimo.
 */
class PrecoMinimo
{
    public const PERMISSAO = 'vendas.alterar_preco';

    /**
     * @param  list<array{produto_id: int, produto_nome: string, quantidade: float, total: float}>  $itens
     *
     * @throws ValidationException
     */
    public static function validar(array $itens, float $descontoGeral): void
    {
        $usuario = auth()->user();
        if ($itens === [] || ! $usuario || $usuario->pode(self::PERMISSAO)) {
            return;
        }

        $minimos = Produto::query()
            ->whereIn('id', array_unique(array_column($itens, 'produto_id')))
            ->where('preco_minimo', '>', 0)
            ->pluck('preco_minimo', 'id');

        if ($minimos->isEmpty()) {
            return;
        }

        $subtotal = round(array_sum(array_column($itens, 'total')), 2);
        $fator = $subtotal > 0 ? max(0.0, 1 - $descontoGeral / $subtotal) : 1.0;

        $abaixo = [];
        foreach ($itens as $item) {
            $minimo = round((float) ($minimos[$item['produto_id']] ?? 0), 2);
            $quantidade = (float) $item['quantidade'];
            if ($minimo <= 0 || $quantidade <= 0) {
                continue;
            }

            $liquido = round((float) $item['total'] * $fator / $quantidade, 2);
            if ($liquido < $minimo) {
                $abaixo[$item['produto_id']] = '"'.$item['produto_nome'].'" (R$ '.self::moeda($liquido)
                    .' por unidade; mínimo R$ '.self::moeda($minimo).')';
            }
        }

        if ($abaixo === []) {
            return;
        }

        throw ValidationException::withMessages([
            'itens' => 'Preço abaixo do mínimo para '.implode(', ', $abaixo)
                .'. Ajuste o preço ou o desconto, ou peça a um usuário com a permissão "Vender abaixo do preço mínimo".',
        ]);
    }

    private static function moeda(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }
}
