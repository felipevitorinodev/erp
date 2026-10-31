<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para autocomplete (empresa + ativo + ordem por nome) e filtros de período.
 * Somente índices não únicos: não alteram dados nem falham por duplicidade.
 *
 * Em tabelas grandes o ALTER pode travar a escrita por alguns segundos.
 * Rodar fora do horário de uso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->index(['empresa_id', 'ativo', 'nome'], 'clientes_empresa_ativo_nome_index');
        });

        Schema::table('fornecedores', function (Blueprint $table) {
            $table->index(['empresa_id', 'ativo', 'nome'], 'fornecedores_empresa_ativo_nome_index');
        });

        Schema::table('funcionarios', function (Blueprint $table) {
            $table->index(['empresa_id', 'ativo', 'nome'], 'funcionarios_empresa_ativo_nome_index');
        });

        Schema::table('grupos', function (Blueprint $table) {
            $table->index(['empresa_id', 'ativo', 'nome'], 'grupos_empresa_ativo_nome_index');
        });

        Schema::table('unidades_medida', function (Blueprint $table) {
            $table->index(['empresa_id', 'ativo', 'nome'], 'unidades_medida_empresa_ativo_nome_index');
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            $table->index(['empresa_id', 'data_orcamento'], 'orcamentos_empresa_data_index');
        });

        Schema::table('ordens_servico', function (Blueprint $table) {
            $table->index(['empresa_id', 'data_abertura'], 'ordens_servico_empresa_data_index');
        });

        Schema::table('entradas_estoque', function (Blueprint $table) {
            $table->index(['empresa_id', 'data_entrada'], 'entradas_estoque_empresa_data_index');
        });

        Schema::table('comissoes', function (Blueprint $table) {
            $table->index(['empresa_id', 'funcionario_id'], 'comissoes_empresa_funcionario_index');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('clientes_empresa_ativo_nome_index');
        });

        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropIndex('fornecedores_empresa_ativo_nome_index');
        });

        Schema::table('funcionarios', function (Blueprint $table) {
            $table->dropIndex('funcionarios_empresa_ativo_nome_index');
        });

        Schema::table('grupos', function (Blueprint $table) {
            $table->dropIndex('grupos_empresa_ativo_nome_index');
        });

        Schema::table('unidades_medida', function (Blueprint $table) {
            $table->dropIndex('unidades_medida_empresa_ativo_nome_index');
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            $table->dropIndex('orcamentos_empresa_data_index');
        });

        Schema::table('ordens_servico', function (Blueprint $table) {
            $table->dropIndex('ordens_servico_empresa_data_index');
        });

        Schema::table('entradas_estoque', function (Blueprint $table) {
            $table->dropIndex('entradas_estoque_empresa_data_index');
        });

        Schema::table('comissoes', function (Blueprint $table) {
            $table->dropIndex('comissoes_empresa_funcionario_index');
        });
    }
};
