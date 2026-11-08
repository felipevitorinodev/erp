<?php

namespace App\Comandos\Rotinas;

/** "cadastrar produto Coca lata a 6,50 estoque 10", "novo produto parafuso 8mm por 1,20". */
final class CadastrarProduto extends CadastrarItem
{
    public function chave(): string
    {
        return 'cadastrar_produto';
    }

    public function titulo(): string
    {
        return 'Cadastrar produto';
    }

    public function atalho(): string
    {
        return 'cadastrar produto';
    }

    public function exemplos(): array
    {
        return [
            'cadastrar produto Coca lata a 6,50',
            'novo produto parafuso 8mm por 1,20 estoque 50',
        ];
    }

    public function palavras(): array
    {
        return ['cadastrar', 'cadastre', 'cadastra', 'produto', 'produtos', 'preco', 'estoque'];
    }

    public function padrao(): string
    {
        return '/^(?:(?:cadastr|cri|adicion|inclu|registr|salv)\w*\s+(?:(?:o|a|um|uma|novo|nova)\s+)?produto|nov[oa]\s+produto|produto\s+nov[oa])\b/u';
    }

    protected function substantivo(): string
    {
        return 'produto';
    }

    protected function palavraChave(): string
    {
        return 'produto';
    }

    protected function tipo(): string
    {
        return '1';
    }

    protected function controlaEstoque(): bool
    {
        return true;
    }

    protected function perguntaPreco(): string
    {
        return 'Qual o preço de venda?';
    }
}
