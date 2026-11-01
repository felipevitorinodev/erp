<?php

namespace App\Support;

use App\Models\Usuarios\User;
use InvalidArgumentException;

class CatalogoRelatorios
{
    /**
     * Intervalo máximo do período personalizado.
     * O atalho de títulos vencidos da home fica de fora: ele precisa do histórico completo.
     */
    public const PERIODO_MAXIMO_DIAS = 366;

    /**
     * @return array<string, array{titulo: string, icone: string, ordem: int}>
     */
    public static function categorias(): array
    {
        return [
            'vendas' => [
                'titulo' => 'Vendas',
                'icone' => 'trending-up',
                'ordem' => 1,
            ],
            'produtos-estoque' => [
                'titulo' => 'Produtos e estoque',
                'icone' => 'package',
                'ordem' => 2,
            ],
            'financeiro' => [
                'titulo' => 'Financeiro',
                'icone' => 'dollar',
                'ordem' => 3,
            ],
        ];
    }

    /**
     * @return list<array{
     *     slug: string,
     *     titulo: string,
     *     descricao: string,
     *     categoria: string,
     *     icone: string,
     *     rota: string,
     *     permissoes: list<string>,
     *     exportacoes: list<string>,
     *     ativo: bool
     * }>
     */
    public static function relatorios(): array
    {
        return [
            [
                'slug' => 'vendas',
                'titulo' => 'Vendas por período',
                'descricao' => 'Vendas do período com faturamento, ticket médio e situação.',
                'categoria' => 'vendas',
                'icone' => 'trending-up',
                'rota' => 'relatorio.vendas',
                'permissoes' => ['relatorios'],
                'exportacoes' => ['pdf', 'csv'],
                'ativo' => true,
            ],
            [
                'slug' => 'produtos-mais-vendidos',
                'titulo' => 'Produtos mais vendidos',
                'descricao' => 'Ranking dos produtos em vendas confirmadas, por quantidade ou valor.',
                'categoria' => 'vendas',
                'icone' => 'bar-chart',
                'rota' => 'relatorio.produtos-mais-vendidos',
                'permissoes' => ['relatorios'],
                'exportacoes' => ['pdf', 'csv'],
                'ativo' => true,
            ],
            [
                'slug' => 'vendas-por-vendedor',
                'titulo' => 'Vendas por vendedor',
                'descricao' => 'Faturamento das vendas confirmadas agrupado por vendedor.',
                'categoria' => 'vendas',
                'icone' => 'trending-up',
                'rota' => 'relatorio.vendas-por-vendedor',
                'permissoes' => ['relatorios'],
                'exportacoes' => ['pdf', 'csv'],
                'ativo' => true,
            ],
            [
                'slug' => 'vendas-por-dia-semana',
                'titulo' => 'Vendas por dia da semana',
                'descricao' => 'Faturamento das vendas confirmadas em cada dia da semana.',
                'categoria' => 'vendas',
                'icone' => 'history',
                'rota' => 'relatorio.vendas-por-dia-semana',
                'permissoes' => ['relatorios'],
                'exportacoes' => ['pdf', 'csv'],
                'ativo' => true,
            ],
            [
                'slug' => 'ranking-clientes',
                'titulo' => 'Ranking de clientes',
                'descricao' => 'Curva ABC dos clientes pelo faturamento das vendas confirmadas.',
                'categoria' => 'vendas',
                'icone' => 'bar-chart',
                'rota' => 'relatorio.ranking-clientes',
                'permissoes' => ['relatorios'],
                'exportacoes' => ['pdf', 'csv'],
                'ativo' => true,
            ],
            [
                'slug' => 'estoque',
                'titulo' => 'Posição de estoque',
                'descricao' => 'Saldo atual, itens críticos e valor em custo.',
                'categoria' => 'produtos-estoque',
                'icone' => 'package',
                'rota' => 'relatorio.estoque',
                'permissoes' => ['relatorios'],
                'exportacoes' => ['pdf', 'csv'],
                'ativo' => true,
            ],
            [
                'slug' => 'contas-receber',
                'titulo' => 'Contas a receber',
                'descricao' => 'Títulos a receber por vencimento, em aberto e vencidos.',
                'categoria' => 'financeiro',
                'icone' => 'dollar',
                'rota' => 'relatorio.contas-receber',
                'permissoes' => ['relatorios', 'financeiro'],
                'exportacoes' => ['pdf', 'csv'],
                'ativo' => true,
            ],
            [
                'slug' => 'contas-pagar',
                'titulo' => 'Contas a pagar',
                'descricao' => 'Títulos a pagar por vencimento, em aberto e vencidos.',
                'categoria' => 'financeiro',
                'icone' => 'dollar',
                'rota' => 'relatorio.contas-pagar',
                'permissoes' => ['relatorios', 'financeiro'],
                'exportacoes' => ['pdf', 'csv'],
                'ativo' => true,
            ],
            [
                'slug' => 'inadimplencia-receber',
                'titulo' => 'Inadimplência a receber',
                'descricao' => 'Títulos a receber em aberto, por faixa de atraso.',
                'categoria' => 'financeiro',
                'icone' => 'alert-triangle',
                'rota' => 'relatorio.inadimplencia-receber',
                'permissoes' => ['relatorios', 'financeiro'],
                'exportacoes' => ['pdf', 'csv'],
                'ativo' => true,
            ],
            [
                'slug' => 'inadimplencia-pagar',
                'titulo' => 'Inadimplência a pagar',
                'descricao' => 'Títulos a pagar em aberto, por faixa de atraso.',
                'categoria' => 'financeiro',
                'icone' => 'alert-triangle',
                'rota' => 'relatorio.inadimplencia-pagar',
                'permissoes' => ['relatorios', 'financeiro'],
                'exportacoes' => ['pdf', 'csv'],
                'ativo' => true,
            ],
            [
                'slug' => 'clientes-com-debito',
                'titulo' => 'Clientes com débito',
                'descricao' => 'Saldo em aberto de cada cliente, com a parte já vencida.',
                'categoria' => 'financeiro',
                'icone' => 'dollar',
                'rota' => 'relatorio.clientes-com-debito',
                'permissoes' => ['relatorios', 'financeiro'],
                'exportacoes' => ['pdf', 'csv'],
                'ativo' => true,
            ],
        ];
    }

    public static function periodoMaximoDias(): int
    {
        return self::PERIODO_MAXIMO_DIAS;
    }

    /**
     * @param  array{ativo?: bool, permissoes?: list<string>}  $relatorio
     */
    public static function usuarioPode(User $usuario, array $relatorio): bool
    {
        if (! ($relatorio['ativo'] ?? false)) {
            return false;
        }

        foreach ($relatorio['permissoes'] ?? [] as $permissao) {
            if (! $usuario->pode($permissao)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function acessiveis(User $usuario): array
    {
        return array_values(array_filter(
            self::relatorios(),
            fn (array $relatorio) => self::usuarioPode($usuario, $relatorio),
        ));
    }

    public static function temAlgum(User $usuario): bool
    {
        return self::acessiveis($usuario) !== [];
    }

    /**
     * @return list<array{slug: string, titulo: string, icone: string, ordem: int, relatorios: list<array<string, mixed>>}>
     */
    public static function agrupados(User $usuario): array
    {
        $categorias = self::categorias();
        $porCategoria = [];

        foreach (self::acessiveis($usuario) as $relatorio) {
            $slug = $relatorio['categoria'];

            if (! isset($categorias[$slug])) {
                throw new InvalidArgumentException("Categoria de relatório desconhecida: {$slug}.");
            }

            $porCategoria[$slug][] = $relatorio;
        }

        $grupos = [];

        foreach ($categorias as $slug => $categoria) {
            if (empty($porCategoria[$slug])) {
                continue;
            }

            $grupos[] = [
                'slug' => $slug,
                'titulo' => $categoria['titulo'],
                'icone' => $categoria['icone'],
                'ordem' => $categoria['ordem'],
                'relatorios' => $porCategoria[$slug],
            ];
        }

        usort($grupos, fn (array $a, array $b) => $a['ordem'] <=> $b['ordem']);

        return $grupos;
    }
}
