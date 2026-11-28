<?php

use App\Support\CatalogoNfse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas oficiais da NFS-e Nacional usadas na busca (autocompletar) e na conferência antes do envio:
 * lista de serviços (Anexo B), NBS 2.0 (Anexo B), cClassTrib do IBS/CBS (tabela RTC, só os códigos aceitos na NFS-e),
 * indicadores de operação (Anexo VII da NT 009), correlação item × NBS × cIndOp × cClassTrib (Anexo VIII)
 * e municípios do IBGE (Anexo A). Só cria tabelas novas e carrega os dados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfse_servicos', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 6)->unique();
            $table->char('item', 5)->index();
            $table->text('descricao');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('nfse_nbs', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 9)->unique();
            $table->string('descricao', 500);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('nfse_classificacoes', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 6)->unique();
            $table->char('cst', 3);
            $table->string('cst_descricao', 255);
            $table->string('descricao', 255);
            $table->boolean('exige_tributacao')->default(true);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('nfse_indicadores_operacao', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 6)->unique();
            $table->string('descricao', 500);
            $table->string('local', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('nfse_correlacoes', function (Blueprint $table) {
            $table->id();
            $table->char('item', 5);
            $table->char('nbs', 9);
            $table->char('ind_op', 6)->nullable();
            $table->char('class_trib', 6)->nullable();
            $table->timestamps();

            $table->unique(['item', 'nbs']);
        });

        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 7)->unique();
            $table->string('nome', 120);
            $table->char('uf', 2);
            $table->timestamps();

            $table->index(['uf', 'nome']);
        });

        CatalogoNfse::sincronizar();
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
        Schema::dropIfExists('nfse_correlacoes');
        Schema::dropIfExists('nfse_indicadores_operacao');
        Schema::dropIfExists('nfse_classificacoes');
        Schema::dropIfExists('nfse_nbs');
        Schema::dropIfExists('nfse_servicos');
    }
};
