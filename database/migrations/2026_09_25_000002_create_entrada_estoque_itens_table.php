<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrada_estoque_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('entrada_estoque_id')
                ->constrained('entradas_estoque')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('produto_id')
                ->nullable()
                ->constrained('produtos')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('produto_nome', 200);
            $table->string('produto_codigo', 50)->nullable();
            $table->decimal('quantidade', 10, 3);
            $table->decimal('preco_unitario', 12, 4);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrada_estoque_itens');
    }
};
