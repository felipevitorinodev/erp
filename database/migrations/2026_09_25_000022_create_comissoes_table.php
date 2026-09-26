<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comissoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('venda_id')
                ->constrained('vendas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('funcionario_id')
                ->constrained('funcionarios')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->decimal('percentual', 5, 2);
            $table->decimal('valor', 12, 2);

            $table->enum('situacao', ['pendente', 'paga'])->default('pendente');
            $table->date('data_pagamento')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comissoes');
    }
};
