<?php

namespace App\Support;

class NaturezasOperacao
{
    /**
     * Textos de natOp usados nesta emissão (venda interna, CFOP série 5).
     * O campo continua livre: o operador pode digitar outro texto de até 60 caracteres.
     *
     * @return list<string>
     */
    public static function lista(): array
    {
        return [
            'VENDA DE MERCADORIA',
            'VENDA DE MERCADORIA ADQUIRIDA DE TERCEIROS',
            'VENDA DE PRODUÇÃO DO ESTABELECIMENTO',
            'VENDA DE COMBUSTÍVEL',
            'REMESSA EM BONIFICAÇÃO',
            'REMESSA DE AMOSTRA GRÁTIS',
        ];
    }

    /**
     * @return list<array{nome: string}>
     */
    public static function buscar(string $termo): array
    {
        $termo = self::semAcento(mb_strtolower(trim($termo)));

        $itens = array_values(array_filter(self::lista(), function (string $nome) use ($termo) {
            if ($termo === '') {
                return true;
            }

            return str_contains(self::semAcento(mb_strtolower($nome)), $termo);
        }));

        return array_map(fn (string $nome) => ['nome' => $nome], $itens);
    }

    private static function semAcento(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);
    }
}
