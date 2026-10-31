<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de contexto do perfil. Ficam vazios nos perfis já cadastrados,
 * e um campo vazio continua casando com qualquer valor.
 * vendas.nfe_tpag guarda o código usado na emissão. Notas antigas permanecem nulas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfis_fiscais', function (Blueprint $table) {
            $table->string('operacao', 20)->nullable()->after('nome');
            $table->char('uf_destino', 2)->nullable()->after('operacao');
            $table->string('tipo_destinatario', 20)->nullable()->after('uf_destino');
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->char('nfe_tpag', 2)->nullable()->after('forma_pagamento');
        });
    }

    public function down(): void
    {
        Schema::table('perfis_fiscais', function (Blueprint $table) {
            $table->dropColumn(['operacao', 'uf_destino', 'tipo_destinatario']);
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('nfe_tpag');
        });
    }
};
