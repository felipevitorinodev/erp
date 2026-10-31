<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga ou desliga a emissão de NFC-e ao finalizar a venda no PDV.
 * O down() só remove esta coluna. Não altera notas já gravadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->boolean('nfce_emitir_pdv')->default(false)->after('nfce_id_csc_producao');
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn('nfce_emitir_pdv');
        });
    }
};
