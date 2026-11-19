<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_receber', function (Blueprint $table) {
            $table->date('proxima_cobranca_em')->nullable()->index();
        });

        Schema::create('cobrancas_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('conta_receber_id')->constrained('contas_receber')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('canal', 20);
            $table->text('resultado');
            $table->date('contatado_em');
            $table->date('proxima_cobranca_em')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'proxima_cobranca_em']);
            $table->index(['conta_receber_id', 'contatado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas_historico');
        Schema::table('contas_receber', function (Blueprint $table) {
            $table->dropColumn('proxima_cobranca_em');
        });
    }
};
