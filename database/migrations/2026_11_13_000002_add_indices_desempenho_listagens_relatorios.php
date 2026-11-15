<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para listagens, contagens da paginação, buscas e relatórios com muito volume.
 * Só cria índices (nenhum dado é alterado). No MySQL 8 o ADD INDEX é online (INPLACE, sem travar escrita).
 *
 * Padrões atendidos (medidos com 1 milhão de vendas, 3,3 milhões de itens, 380 mil contas):
 *  - Listagem: WHERE empresa_id = ? AND deleted_at IS NULL [AND filtros] ORDER BY id DESC LIMIT 20 + COUNT(*).
 *    (empresa_id, deleted_at, id, filtros...): a página é lida em ordem direto do índice e o COUNT não lê linhas.
 *    O id aparece explícito para fixar a ordem; o InnoDB não o duplica (já faz parte de todo índice secundário).
 *  - Relatórios e painel: índice "cobrindo" (covering) com o período, a situação e as colunas somadas, para
 *    SUM/COUNT/GROUP BY lerem só o índice em vez de buscar cada linha na tabela.
 *  - Ordenação por nome (clientes, fornecedores, produtos): índice já ordenado, sem filesort.
 */
return new class extends Migration
{
    /** @var array<string, array<string, list<string>>> tabela => [nome do índice => colunas] */
    private const INDICES = [
        'vendas' => [
            // Listagem: ORDER BY id DESC da empresa vira leitura reversa de 20 entradas deste índice (antes a chave
            // primária era percorrida passando pelas vendas das outras empresas); situação, cliente, número e data
            // são filtrados dentro do índice, e o COUNT da paginação também sai só dele.
            'vendas_lista_index' => ['empresa_id', 'deleted_at', 'id', 'situacao', 'cliente_id', 'numero', 'data_venda'],
            // Busca rápida de vendas (autocompletar): ORDER BY numero LIMIT 10 para nas primeiras vendas que batem.
            'vendas_numero_index' => ['empresa_id', 'deleted_at', 'numero', 'situacao', 'cliente_id'],
            // Relatórios de vendas, painel, ranking, vendedores, dia da semana e fluxo de caixa. Com numero e id logo
            // após a data, o ORDER BY data_venda, numero, id do relatório sai pronto do índice (sem ordenar o ano
            // inteiro para mostrar 20 linhas); o InnoDB não duplica o id, que já faz parte de todo índice secundário.
            'vendas_relatorio_index' => ['empresa_id', 'data_venda', 'numero', 'id', 'situacao', 'deleted_at', 'total', 'cliente_id', 'funcionario_id', 'forma_pagamento_id', 'forma_pagamento'],
        ],
        'venda_itens' => [
            // Produtos mais vendidos: soma por produto sem ler as linhas dos itens (3 milhões de linhas no teste).
            'venda_itens_relatorio_index' => ['venda_id', 'produto_nome', 'produto_codigo', 'produto_id', 'quantidade', 'total'],
        ],
        'contas_receber' => [
            'contas_receber_lista_index' => ['empresa_id', 'deleted_at', 'id', 'situacao', 'cliente_id', 'data_vencimento'],
            'contas_receber_relatorio_index' => ['empresa_id', 'situacao', 'data_vencimento', 'deleted_at', 'valor', 'valor_pago', 'cliente_id', 'categoria_id'],
            'contas_receber_periodo_index' => ['empresa_id', 'data_vencimento', 'deleted_at', 'situacao', 'valor', 'valor_pago', 'categoria_id'],
        ],
        'contas_pagar' => [
            'contas_pagar_lista_index' => ['empresa_id', 'deleted_at', 'id', 'situacao', 'fornecedor_id', 'data_vencimento'],
            'contas_pagar_relatorio_index' => ['empresa_id', 'situacao', 'data_vencimento', 'deleted_at', 'valor', 'valor_pago', 'fornecedor_id', 'categoria_id'],
            'contas_pagar_periodo_index' => ['empresa_id', 'data_vencimento', 'deleted_at', 'situacao', 'valor', 'valor_pago', 'categoria_id'],
        ],
        'clientes' => [
            'clientes_lista_index' => ['empresa_id', 'deleted_at', 'nome', 'ativo'],
        ],
        'fornecedores' => [
            'fornecedores_lista_index' => ['empresa_id', 'deleted_at', 'nome', 'ativo'],
        ],
        'produtos' => [
            'produtos_lista_index' => ['empresa_id', 'deleted_at', 'nome', 'ativo'],
        ],
        'comissoes' => [
            // Listagem por id decrescente e totais por situação (SUM(valor)) só no índice.
            'comissoes_lista_index' => ['empresa_id', 'id', 'situacao', 'funcionario_id', 'venda_id', 'valor'],
        ],
        'orcamentos' => [
            'orcamentos_lista_index' => ['empresa_id', 'deleted_at', 'id', 'situacao', 'cliente_id', 'numero', 'data_orcamento'],
        ],
        'ordens_servico' => [
            'ordens_servico_lista_index' => ['empresa_id', 'deleted_at', 'id', 'situacao', 'cliente_id', 'numero', 'funcionario_id', 'data_abertura'],
        ],
        'entradas_estoque' => [
            'entradas_estoque_lista_index' => ['empresa_id', 'deleted_at', 'id', 'situacao', 'fornecedor_id', 'numero', 'data_entrada'],
        ],
        'nfes' => [
            'nfes_lista_index' => ['empresa_id', 'modelo', 'deleted_at'],
        ],
        'movimentacoes_estoque' => [
            // Histórico de estoque: ORDER BY id DESC da empresa com filtros de produto, tipo e data dentro do índice.
            'movimentacoes_estoque_lista_index' => ['empresa_id', 'id', 'produto_id', 'tipo', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabela => $indices) {
            foreach ($indices as $nome => $colunas) {
                if (Schema::hasIndex($tabela, $nome)) {
                    continue;
                }

                Schema::table($tabela, fn (Blueprint $table) => $table->index($colunas, $nome));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as $tabela => $indices) {
            foreach (array_keys($indices) as $nome) {
                if (Schema::hasIndex($tabela, $nome)) {
                    Schema::table($tabela, fn (Blueprint $table) => $table->dropIndex($nome));
                }
            }
        }
    }
};
