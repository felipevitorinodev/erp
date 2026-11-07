<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendas', function (Blueprint $table) {
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

            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('numero', 20)->nullable()->comment('Número sequencial da venda');

            $table->date('data_venda');
            $table->date('data_entrega')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('acrescimo', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('forma_pagamento', 100)->nullable();
            $table->text('observacoes')->nullable();

            // em_andamento | finalizada | cancelada
            $table->string('situacao', 20)->default('em_andamento');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('venda_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('venda_id')
                ->constrained('vendas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('produto_id')
                ->nullable()
                ->constrained('produtos')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('produto_nome')->comment('Nome snapshot no momento da venda');
            $table->string('produto_codigo', 50)->nullable();

            $table->decimal('quantidade', 12, 3)->default(1);
            $table->decimal('preco_unitario', 12, 4)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venda_itens');
        Schema::dropIfExists('vendas');
    }
};
