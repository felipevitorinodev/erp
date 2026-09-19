<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_receber', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('clientes')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('venda_id')
                ->nullable()
                ->constrained('vendas')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('descricao', 200);
            $table->decimal('valor', 12, 2);
            $table->decimal('valor_pago', 12, 2)->default(0);
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable();
            $table->string('forma_pagamento', 100)->nullable();
            $table->string('situacao', 20)->default('aberta');
            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_receber');
    }
};
