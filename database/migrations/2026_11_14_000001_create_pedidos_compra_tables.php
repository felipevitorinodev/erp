<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete()->cascadeOnUpdate();
            $table->unsignedBigInteger('numero_seq');
            $table->string('numero', 20);
            $table->date('data_emissao');
            $table->date('data_prevista')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->enum('situacao', ['rascunho', 'aprovado', 'parcial', 'recebido', 'cancelado'])->default('rascunho');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'numero_seq']);
            $table->index(['empresa_id', 'situacao', 'id']);
        });

        Schema::create('pedido_compra_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_compra_id')->constrained('pedidos_compra')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete()->cascadeOnUpdate();
            $table->string('produto_nome', 200);
            $table->string('produto_codigo', 50)->nullable();
            $table->decimal('quantidade', 10, 3);
            $table->decimal('quantidade_recebida', 10, 3)->default(0);
            $table->decimal('preco_unitario', 12, 2);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->timestamps();
            $table->index(['pedido_compra_id', 'produto_id']);
        });

        Schema::table('entradas_estoque', function (Blueprint $table) {
            $table->foreignId('pedido_compra_id')
                ->nullable()
                ->after('fornecedor_id')
                ->constrained('pedidos_compra')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('entradas_estoque', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pedido_compra_id');
        });

        Schema::dropIfExists('pedido_compra_itens');
        Schema::dropIfExists('pedidos_compra');
    }
};
