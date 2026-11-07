<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->string('natureza_operacao', 60)->default('VENDA DE MERCADORIA')->after('observacoes');
            $table->unsignedTinyInteger('mod_frete')->default(9)->after('natureza_operacao');
            $table->unsignedTinyInteger('ind_final')->nullable()->after('mod_frete');
            $table->string('inf_ad_fisco', 2000)->nullable()->after('ind_final');
        });

        Schema::table('venda_itens', function (Blueprint $table) {
            $table->string('ncm', 8)->nullable()->after('produto_codigo');
            $table->string('cest', 9)->nullable()->after('ncm');
            $table->string('cfop', 4)->nullable()->after('cest');
            $table->string('unidade', 6)->nullable()->after('cfop');
            $table->unsignedTinyInteger('origem')->default(0)->after('unidade');
            $table->string('csosn', 3)->nullable()->after('origem');
            $table->string('cst_pis', 2)->nullable()->after('csosn');
            $table->string('cst_cofins', 2)->nullable()->after('cst_pis');
            $table->string('ean', 14)->nullable()->after('cst_cofins');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn(['natureza_operacao', 'mod_frete', 'ind_final', 'inf_ad_fisco']);
        });

        Schema::table('venda_itens', function (Blueprint $table) {
            $table->dropColumn([
                'ncm',
                'cest',
                'cfop',
                'unidade',
                'origem',
                'csosn',
                'cst_pis',
                'cst_cofins',
                'ean',
            ]);
        });
    }
};
