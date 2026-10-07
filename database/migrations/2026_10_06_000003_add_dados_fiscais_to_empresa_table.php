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
        Schema::table('empresa', function (Blueprint $table) {
            $table->string('cod_ibge', 7)->nullable()->after('nfe_id_csc');
            $table->unsignedTinyInteger('crt')->default(1)->after('cod_ibge');
            $table->unsignedTinyInteger('nfe_destino')->default(1)->after('crt');
            $table->string('nfe_cfop_padrao', 4)->default('5102')->after('nfe_destino');
            $table->string('nfe_csosn_padrao', 3)->default('400')->after('nfe_cfop_padrao');
            $table->string('nfe_cst_pis_padrao', 2)->default('07')->after('nfe_csosn_padrao');
            $table->string('nfe_cst_cofins_padrao', 2)->default('07')->after('nfe_cst_pis_padrao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn([
                'cod_ibge',
                'crt',
                'nfe_destino',
                'nfe_cfop_padrao',
                'nfe_csosn_padrao',
                'nfe_cst_pis_padrao',
                'nfe_cst_cofins_padrao',
            ]);
        });
    }
};
