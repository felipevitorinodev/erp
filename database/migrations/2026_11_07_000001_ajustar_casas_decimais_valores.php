<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Preços e pagamentos ficam com 2 casas. Quantidade de estoque fica com 3,
 * no mesmo tamanho usado em produtos e vendas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->arredondar('produtos', ['preco_custo', 'preco_venda', 'preco_minimo']);
        $this->arredondar('venda_itens', ['preco_unitario']);
        $this->arredondar('orcamento_itens', ['preco_unitario']);
        $this->arredondar('ordem_servico_itens', ['preco_unitario']);
        $this->arredondar('entrada_estoque_itens', ['preco_unitario']);

        DB::statement("ALTER TABLE produtos MODIFY preco_custo DECIMAL(12,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE produtos MODIFY preco_venda DECIMAL(12,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE produtos MODIFY preco_minimo DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Preço mínimo para venda'");
        DB::statement("ALTER TABLE venda_itens MODIFY preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE orcamento_itens MODIFY preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE ordem_servico_itens MODIFY preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE entrada_estoque_itens MODIFY preco_unitario DECIMAL(12,2) NOT NULL");

        DB::statement("ALTER TABLE movimentacoes_estoque MODIFY quantidade DECIMAL(12,3) NOT NULL");
        DB::statement("ALTER TABLE movimentacoes_estoque MODIFY estoque_antes DECIMAL(12,3) NOT NULL");
        DB::statement("ALTER TABLE movimentacoes_estoque MODIFY estoque_depois DECIMAL(12,3) NOT NULL");
        DB::statement("ALTER TABLE entrada_estoque_itens MODIFY quantidade DECIMAL(12,3) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE produtos MODIFY preco_custo DECIMAL(12,4) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE produtos MODIFY preco_venda DECIMAL(12,4) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE produtos MODIFY preco_minimo DECIMAL(12,4) NOT NULL DEFAULT 0 COMMENT 'Preço mínimo para venda'");
        DB::statement("ALTER TABLE venda_itens MODIFY preco_unitario DECIMAL(12,4) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE orcamento_itens MODIFY preco_unitario DECIMAL(12,4) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE ordem_servico_itens MODIFY preco_unitario DECIMAL(12,4) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE entrada_estoque_itens MODIFY preco_unitario DECIMAL(12,4) NOT NULL");

        DB::statement("ALTER TABLE movimentacoes_estoque MODIFY quantidade DECIMAL(10,3) NOT NULL");
        DB::statement("ALTER TABLE movimentacoes_estoque MODIFY estoque_antes DECIMAL(10,3) NOT NULL");
        DB::statement("ALTER TABLE movimentacoes_estoque MODIFY estoque_depois DECIMAL(10,3) NOT NULL");
        DB::statement("ALTER TABLE entrada_estoque_itens MODIFY quantidade DECIMAL(10,3) NOT NULL");
    }

    private function arredondar(string $tabela, array $colunas): void
    {
        foreach ($colunas as $coluna) {
            DB::statement("UPDATE {$tabela} SET {$coluna} = ROUND({$coluna}, 2)");
        }
    }
};
