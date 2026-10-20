<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CPF/CNPJ de cliente eram únicos no sistema inteiro: uma empresa não conseguia cadastrar
 * um cliente que já existisse em outra. A unicidade passa a ser validada por empresa
 * (ClienteRequest, ignorando excluídos); aqui só troca o índice único global por índices de busca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropUnique(['cnpj']);

            $table->index(['empresa_id', 'cpf'], 'clientes_empresa_cpf_index');
            $table->index(['empresa_id', 'cnpj'], 'clientes_empresa_cnpj_index');
        });
    }

    /**
     * Falha se, depois do up(), duas empresas tiverem cadastrado o mesmo CPF/CNPJ
     * (exatamente o caso que o up() passou a permitir).
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('clientes_empresa_cpf_index');
            $table->dropIndex('clientes_empresa_cnpj_index');

            $table->unique('cpf');
            $table->unique('cnpj');
        });
    }
};
