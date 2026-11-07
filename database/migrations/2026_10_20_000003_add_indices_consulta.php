<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para listagens, dashboard, relatórios e busca de produtos (PDV/importação de XML).
 * Somente índices não únicos: não alteram dados nem podem falhar por duplicidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_receber', function (Blueprint $table) {
            $table->index(['empresa_id', 'situacao', 'data_vencimento'], 'contas_receber_empresa_situacao_venc_index');
        });

        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->index(['empresa_id', 'situacao', 'data_vencimento'], 'contas_pagar_empresa_situacao_venc_index');
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->index(['empresa_id', 'data_venda'], 'vendas_empresa_data_index');
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->index(['empresa_id', 'codigo'], 'produtos_empresa_codigo_index');
            $table->index(['empresa_id', 'codigo_barras'], 'produtos_empresa_codigo_barras_index');
        });

        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->index(['origem', 'origem_id'], 'movimentacoes_estoque_origem_index');
        });
    }

    public function down(): void
    {
        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropIndex('contas_receber_empresa_situacao_venc_index');
        });

        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->dropIndex('contas_pagar_empresa_situacao_venc_index');
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->dropIndex('vendas_empresa_data_index');
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropIndex('produtos_empresa_codigo_index');
            $table->dropIndex('produtos_empresa_codigo_barras_index');
        });

        Schema::table('movimentacoes_estoque', function (Blueprint $table) {
            $table->dropIndex('movimentacoes_estoque_origem_index');
        });
    }
};
