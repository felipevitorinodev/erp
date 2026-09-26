<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entradas_estoque', function (Blueprint $table) {
            $table->string('chave_acesso', 44)->nullable()->after('situacao');
            $table->longText('xml_importado')->nullable()->after('chave_acesso');
            $table->string('numero_nfe', 20)->nullable()->after('xml_importado');
            $table->string('serie_nfe', 3)->nullable()->after('numero_nfe');
            $table->date('data_emissao_nfe')->nullable()->after('serie_nfe');

            $table->unique(['empresa_id', 'chave_acesso'], 'entradas_estoque_empresa_chave_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entradas_estoque', function (Blueprint $table) {
            $table->dropUnique('entradas_estoque_empresa_chave_unique');
            $table->dropColumn([
                'chave_acesso',
                'xml_importado',
                'numero_nfe',
                'serie_nfe',
                'data_emissao_nfe',
            ]);
        });
    }
};
