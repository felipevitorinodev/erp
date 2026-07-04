<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('grupo_id')
                ->nullable()
                ->constrained('grupos')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('unidade_medida_id')
                ->nullable()
                ->constrained('unidades_medida')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('fornecedor_id')
                ->nullable()
                ->comment('Fornecedor principal')
                ->constrained('fornecedores')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('codigo', 50)->nullable()->comment('Código interno');
            $table->string('codigo_barras', 50)->nullable()->comment('EAN/GTIN');
            $table->string('referencia', 50)->nullable();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->tinyInteger('tipo')->default(1)->comment('1=Produto, 2=Servico');

            $table->decimal('preco_custo', 12, 4)->default(0);
            $table->decimal('preco_venda', 12, 4)->default(0);
            $table->decimal('preco_minimo', 12, 4)->default(0)->comment('Preço mínimo para venda');
            $table->decimal('margem_lucro', 6, 2)->nullable();

            $table->decimal('estoque_minimo', 12, 3)->default(0);
            $table->decimal('estoque_maximo', 12, 3)->nullable();
            $table->decimal('estoque_atual', 12, 3)->default(0)->comment('Desnormalizado para performance');

            $table->decimal('peso', 10, 3)->nullable()->comment('em kg');
            $table->decimal('altura', 10, 2)->nullable()->comment('em cm');
            $table->decimal('largura', 10, 2)->nullable()->comment('em cm');
            $table->decimal('profundidade', 10, 2)->nullable()->comment('em cm');

            $table->string('foto_url', 500)->nullable();

            // Dados Fiscais
            $table->string('ncm', 10)->nullable();
            $table->string('cest', 10)->nullable();
            $table->string('cfop_padrao', 5)->nullable();
            $table->tinyInteger('origem')->default(0)->comment('0=Nacional, 1=Estrangeira importação direta, ...');
            $table->string('cst_icms', 5)->nullable();
            $table->string('cst_pis', 3)->nullable();
            $table->string('cst_cofins', 3)->nullable();
            $table->decimal('aliquota_icms', 6, 2)->nullable();
            $table->decimal('aliquota_pis', 6, 4)->nullable();
            $table->decimal('aliquota_cofins', 6, 4)->nullable();

            // Controle
            $table->boolean('controla_estoque')->default(true);
            $table->boolean('ativo')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};