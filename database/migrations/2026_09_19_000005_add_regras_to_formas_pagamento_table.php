<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->string('tipo', 20)->default('avista')->after('nome'); // avista | prazo
            $table->boolean('gera_conta_receber')->default(false)->after('tipo');
            $table->unsignedSmallInteger('dias_vencimento')->default(0)->after('gera_conta_receber');
        });
    }

    public function down(): void
    {
        Schema::table('formas_pagamento', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'gera_conta_receber', 'dias_vencimento']);
        });
    }
};
