<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para relatórios por período, dashboard, fluxo de caixa, comissões e histórico de estoque.
 * Somente índices não únicos: não alteram dados nem podem falhar por duplicidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->index(['empresa_id', 'situacao', 'data_venda'], 'vendas_empresa_situacao_data_index');
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->index(['empresa_id', 'data_vencimento'], 'contas_receber_empresa_venc_index');
        });

        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->index(['empresa_id', 'data_vencimento'], 'contas_pagar_empresa_venc_index');
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->index(['empresa_id', 'ativo', 'nome'], 'produtos_empresa_ativo_nome_index');
        });

        Schema::table('comissoes', function (Blueprint $table) {
            $table->index(['empresa_id', 'situacao'], 'comissoes_empresa_situacao_index');
        });

        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->index(['empresa_id', 'created_at'], 'movimentacoes_estoque_empresa_data_index');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropIndex('vendas_empresa_situacao_data_index');
        });

        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropIndex('contas_receber_empresa_venc_index');
        });

        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->dropIndex('contas_pagar_empresa_venc_index');
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropIndex('produtos_empresa_ativo_nome_index');
        });

        Schema::table('comissoes', function (Blueprint $table) {
            $table->dropIndex('comissoes_empresa_situacao_index');
        });

        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropIndex('movimentacoes_estoque_empresa_data_index');
        });
    }
};
