<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valor total da NF-e importada (vNF: inclui IPI, ST, frete, seguro e outras despesas).
 * Quando preenchido, é o valor da conta a pagar gerada na confirmação. Entradas manuais ficam null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entradas_estoque', function (Blueprint $table) {
            $table->decimal('valor_nota', 12, 2)->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('entradas_estoque', function (Blueprint $table) {
            $table->dropColumn('valor_nota');
        });
    }
};
