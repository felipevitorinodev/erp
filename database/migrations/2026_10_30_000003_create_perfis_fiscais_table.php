<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil fiscal reutilizável. Não copia nem altera CFOP/CSOSN já gravados em produtos ou em notas.
 * O vínculo do produto é opcional; notas autorizadas não são lidas nem regravadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('nome', 80);
            $table->char('ncm', 8)->nullable();
            $table->char('cfop', 4);
            $table->char('csosn', 3);
            $table->char('cst_pis', 2);
            $table->char('cst_cofins', 2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'ncm'], 'perfis_fiscais_empresa_ncm_index');
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->foreignId('perfil_fiscal_id')
                ->nullable()
                ->after('fornecedor_id')
                ->constrained('perfis_fiscais')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('perfil_fiscal_id');
        });

        Schema::dropIfExists('perfis_fiscais');
    }
};
