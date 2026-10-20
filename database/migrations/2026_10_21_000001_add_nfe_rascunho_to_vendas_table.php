<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rascunho de NF-e: a venda fica "em andamento" (sem estoque, sem número de NF-e) até ser emitida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->boolean('nfe_rascunho')->default(false)->after('situacao');
            $table->index(['empresa_id', 'nfe_rascunho', 'situacao'], 'vendas_empresa_nfe_rascunho_index');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropIndex('vendas_empresa_nfe_rascunho_index');
            $table->dropColumn('nfe_rascunho');
        });
    }
};
