<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extratos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('arquivo_nome', 255);
            $table->char('arquivo_hash', 64);
            $table->string('conta_bancaria', 100)->nullable();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'arquivo_hash']);
            $table->index(['empresa_id', 'id']);
        });

        Schema::create('transacoes_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('extrato_bancario_id')->constrained('extratos_bancarios')->cascadeOnUpdate()->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->string('fitid', 255)->nullable();
            $table->date('data_transacao');
            $table->string('tipo', 30)->nullable();
            $table->decimal('valor', 14, 2);
            $table->string('descricao', 255)->default('');
            $table->text('memo')->nullable();
            $table->string('situacao', 20)->default('pendente');
            $table->foreignId('conta_receber_id')->nullable()->constrained('contas_receber')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('conta_pagar_id')->nullable()->constrained('contas_pagar')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('conciliado_em')->nullable();
            $table->string('motivo_ignorado', 500)->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'fingerprint']);
            // Nome explícito: o gerado automaticamente passa do limite de 64 caracteres do MySQL.
            $table->index(['extrato_bancario_id', 'situacao', 'data_transacao'], 'transacoes_bancarias_extrato_situacao_data_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transacoes_bancarias');
        Schema::dropIfExists('extratos_bancarios');
    }
};
