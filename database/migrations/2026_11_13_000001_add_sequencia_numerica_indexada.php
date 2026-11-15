<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Próximo número de venda, orçamento e OS (e próximo código de produto) sem varrer a tabela.
 *
 * Antes: MAX(CAST(numero AS UNSIGNED)) lia todas as linhas da empresa, com a empresa travada (FOR UPDATE),
 * a cada venda gravada; com 1 milhão de vendas isso levava ~1,5 s e enfileirava todos os caixas do PDV.
 * Agora: coluna gerada VIRTUAL (não ocupa espaço na linha, não muda nenhum dado) com o valor numérico do
 * número, e um índice (empresa_id, coluna): o MAX vira uma leitura só no fim do índice.
 *
 * Só números de 1 a 19 dígitos entram (cabe em BIGINT UNSIGNED); o resto fica NULL e é ignorado pelo MAX,
 * como já acontecia com o REGEXP '^[0-9]+$' do código de produto.
 */
return new class extends Migration
{
    /** @var array<string, string> tabela => coluna de origem */
    private const ALVOS = [
        'vendas' => 'numero',
        'orcamentos' => 'numero',
        'ordens_servico' => 'numero',
        'produtos' => 'codigo',
    ];

    public function up(): void
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';

        foreach (self::ALVOS as $tabela => $origem) {
            $coluna = $origem.'_seq';
            if (Schema::hasColumn($tabela, $coluna)) {
                continue;
            }

            if ($sqlite) {
                DB::statement("ALTER TABLE {$tabela} ADD COLUMN {$coluna} INTEGER GENERATED ALWAYS AS (CASE WHEN {$origem} <> '' AND length({$origem}) <= 19 AND {$origem} NOT GLOB '*[^0-9]*' THEN CAST({$origem} AS INTEGER) END) VIRTUAL");
            } else {
                // ADD COLUMN VIRTUAL é instantâneo; o índice é criado online (ALGORITHM=INPLACE, LOCK=NONE).
                DB::statement("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`{$origem}` REGEXP '^[0-9]{1,19}$', CAST(`{$origem}` AS UNSIGNED), NULL)) VIRTUAL");
            }

            DB::statement("CREATE INDEX {$tabela}_empresa_{$coluna}_index ON {$tabela} (empresa_id, {$coluna})");
        }
    }

    public function down(): void
    {
        foreach (self::ALVOS as $tabela => $origem) {
            $coluna = $origem.'_seq';
            if (! Schema::hasColumn($tabela, $coluna)) {
                continue;
            }

            Schema::table($tabela, function ($table) use ($tabela, $coluna) {
                $table->dropIndex($tabela.'_empresa_'.$coluna.'_index');
            });
            Schema::table($tabela, function ($table) use ($coluna) {
                $table->dropColumn($coluna);
            });
        }
    }
};
