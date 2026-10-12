<?php

namespace App\Support;

class CatalogoPermissoes
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function grupos(): array
    {
        return [
            'Vendas' => [
                'vendas' => 'PDV e vendas',
                'vendas.ver_canceladas' => 'Ver vendas canceladas',
                'vendas.cancelar' => 'Cancelar ou excluir venda confirmada',
                'orcamentos' => 'Orçamentos',
                'comissoes' => 'Comissões',
                'nfe' => 'NF-e',
            ],
            'Estoque e produtos' => [
                'produtos' => 'Produtos, grupos e unidades',
                'estoque' => 'Entradas e ajuste de estoque',
            ],
            'Cadastros' => [
                'clientes' => 'Clientes',
                'clientes.balcao' => 'Cadastrar cliente rápido no balcão',
                'fornecedores' => 'Fornecedores',
                'funcionarios' => 'Funcionários',
            ],
            'Financeiro' => [
                'financeiro' => 'Contas, fluxo de caixa e categorias',
                'formas_pagamento' => 'Formas de pagamento',
                'relatorios' => 'Relatórios',
            ],
            'Configurações' => [
                'configuracoes' => 'Usuários, empresa e permissões',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function chaves(): array
    {
        $chaves = [];
        foreach (self::grupos() as $permissoes) {
            foreach ($permissoes as $chave => $rotulo) {
                $chaves[] = $chave;
            }
        }

        return $chaves;
    }

    /**
     * @return list<string>
     */
    public static function padrao(string $slug): array
    {
        return match ($slug) {
            'admin' => self::chaves(),
            'financeiro' => [
                'vendas',
                'vendas.ver_canceladas',
                'vendas.cancelar',
                'orcamentos',
                'nfe',
                'produtos',
                'estoque',
                'clientes',
                'clientes.balcao',
                'fornecedores',
                'financeiro',
                'relatorios',
            ],
            'vendedor', 'operador' => [
                'vendas',
                'clientes.balcao',
            ],
            default => [],
        };
    }
}
