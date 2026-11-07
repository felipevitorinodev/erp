<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->unsignedBigInteger('entrada_estoque_id')->nullable()->after('fornecedor_id');

            $table->foreign('entrada_estoque_id')
                ->references('id')
                ->on('entradas_estoque')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('contas_pagar', function (Blueprint $table) {
            $table->dropForeign(['entrada_estoque_id']);
            $table->dropColumn('entrada_estoque_id');
        });
    }
};
