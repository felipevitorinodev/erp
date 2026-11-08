<?php

namespace App\Comandos\Rotinas;

/** "cadastrar serviço troca de tela 150", "novo serviço mão de obra por 80". */
final class CadastrarServico extends CadastrarItem
{
    public function chave(): string
    {
        return 'cadastrar_servico';
    }

    public function titulo(): string
    {
        return 'Cadastrar serviço';
    }

    public function atalho(): string
    {
        return 'cadastrar serviço';
    }

    public function exemplos(): array
    {
        return [
            'cadastrar serviço troca de tela 150',
            'novo serviço mão de obra por 80',
        ];
    }

    public function palavras(): array
    {
        return ['cadastrar', 'cadastre', 'cadastra', 'servico', 'servicos', 'preco'];
    }

    public function padrao(): string
    {
        return '/^(?:(?:cadastr|cri|adicion|inclu|registr|salv)\w*\s+(?:(?:o|a|um|uma|novo|nova)\s+)?servicos?|nov[oa]\s+servicos?|servicos?\s+nov[oa])\b/u';
    }

    protected function substantivo(): string
    {
        return 'serviço';
    }

    protected function palavraChave(): string
    {
        return 'servico';
    }

    protected function tipo(): string
    {
        return '2';
    }

    protected function controlaEstoque(): bool
    {
        return false;
    }

    protected function perguntaPreco(): string
    {
        return 'Qual o preço do serviço?';
    }
}
