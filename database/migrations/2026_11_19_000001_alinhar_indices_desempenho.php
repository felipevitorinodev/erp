<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A 2026_11_13_000002 foi alterada depois de já ter rodado no banco local: lá ficaram os índices de listagem sem o
 * id (que fixa a ordem da página), o relatório de vendas sem o id, o antigo comissoes_totais_index e nenhum
 * vendas_numero_index. Como aquela migration pula índice que já existe pelo nome, esses bancos nunca recebem a
 * versão nova. Aqui cada índice é refeito só quando as colunas diferem; num banco criado do zero nada muda.
 */
return new class extends Migration
{
    /** @var array<string, array<string, list<string>>> tabela => [nome do índice => colunas] */
    private const INDICES = [
        'vendas' => [
            'vendas_lista_index' => ['empresa_id', 'deleted_at', 'id', 'situacao', 'cliente_id', 'numero', 'data_venda'],
            'vendas_numero_index' => ['empresa_id', 'deleted_at', 'numero', 'situacao', 'cliente_id'],
            'vendas_relatorio_index' => ['empresa_id', 'data_venda', 'numero', 'id', 'situacao', 'deleted_at', 'total', 'cliente_id', 'funcionario_id', 'forma_pagamento_id', 'forma_pagamento'],
        ],
        'contas_receber' => [
            'contas_receber_lista_index' => ['empresa_id', 'deleted_at', 'id', 'situacao', 'cliente_id', 'data_vencimento'],
        ],
        'contas_pagar' => [
            'contas_pagar_lista_index' => ['empresa_id', 'deleted_at', 'id', 'situacao', 'fornecedor_id', 'data_vencimento'],
        ],
        'comissoes' => [
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
        'movimentacoes_estoque' => [
            'movimentacoes_estoque_lista_index' => ['empresa_id', 'id', 'produto_id', 'tipo', 'created_at'],
        ],
    ];

    /** Substituído pelo comissoes_lista_index, que já cobre os totais por situação. */
    private const OBSOLETOS = [
        'comissoes' => ['comissoes_totais_index'],
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabela => $indices) {
            $atuais = collect(Schema::getIndexes($tabela))->keyBy('name');

            foreach ($indices as $nome => $colunas) {
                $atual = $atuais->get($nome);

                if ($atual === null) {
                    Schema::table($tabela, fn (Blueprint $table) => $table->index($colunas, $nome));
                } elseif ($atual['columns'] !== $colunas) {
                    $this->recriar($tabela, $nome, $colunas);
                }
            }
        }

        // Depois de criar os novos: assim a chave estrangeira de empresa_id nunca fica sem índice.
        foreach (self::OBSOLETOS as $tabela => $nomes) {
            foreach ($nomes as $nome) {
                if (Schema::hasIndex($tabela, $nome)) {
                    Schema::table($tabela, fn (Blueprint $table) => $table->dropIndex($nome));
                }
            }
        }
    }

    /**
     * Sem volta: o formato correto é o da 2026_11_13_000002, cujo down() remove esses índices.
     */
    public function down(): void {}

    /**
     * No MySQL, DROP e ADD no mesmo ALTER: o índice pode ser o que atende a chave estrangeira de empresa_id,
     * e removê-lo sozinho seria recusado.
     *
     * @param  list<string>  $colunas
     */
    private function recriar(string $tabela, string $nome, array $colunas): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table($tabela, fn (Blueprint $table) => $table->dropIndex($nome));
            Schema::table($tabela, fn (Blueprint $table) => $table->index($colunas, $nome));

            return;
        }

        $gramatica = DB::connection()->getSchemaGrammar();

        DB::statement(sprintf(
            'alter table %s drop index %s, add index %s(%s)',
            $gramatica->wrapTable($tabela),
            $gramatica->wrap($nome),
            $gramatica->wrap($nome),
            $gramatica->columnize($colunas),
        ));
    }
};
