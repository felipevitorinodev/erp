<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordens_servico', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            // técnico responsável
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('orcamento_id')->nullable()->constrained('orcamentos')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete()->cascadeOnUpdate();

            $table->string('numero', 20)->nullable();
            $table->date('data_abertura');
            $table->date('data_previsao')->nullable();
            $table->date('data_conclusao')->nullable();

            $table->string('equipamento', 150)->nullable();
            $table->string('marca', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('numero_serie', 80)->nullable();
            $table->string('acessorios', 255)->nullable();
            $table->text('defeito_relatado')->nullable();
            $table->text('diagnostico')->nullable();
            $table->text('servico_executado')->nullable();
            $table->string('garantia', 100)->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('acrescimo', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('forma_pagamento', 100)->nullable();
            $table->text('observacoes')->nullable();

            // aberta | em_execucao | aguardando_peca | concluida | faturada | cancelada
            $table->string('situacao', 20)->default('aberta');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'situacao']);
            $table->index(['empresa_id', 'numero']);
        });

        Schema::create('ordem_servico_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ordem_servico_id')
                ->constrained('ordens_servico')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete()->cascadeOnUpdate();

            $table->string('produto_nome');
            $table->string('produto_codigo', 50)->nullable();

            $table->decimal('quantidade', 12, 3)->default(1);
            $table->decimal('preco_unitario', 12, 4)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->timestamps();
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            $table->foreignId('ordem_servico_id')
                ->nullable()
                ->after('venda_id')
                ->constrained('ordens_servico')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        // Perfis que já usam orçamentos passam a ver ordens de serviço (admin já tem tudo).
        DB::table('perfis')->orderBy('id')->each(function ($perfil) {
            $permissoes = json_decode((string) $perfil->permissoes, true);

            if (is_array($permissoes) && in_array('orcamentos', $permissoes, true) && ! in_array('ordens_servico', $permissoes, true)) {
                $permissoes[] = 'ordens_servico';
                DB::table('perfis')->where('id', $perfil->id)->update(['permissoes' => json_encode(array_values($permissoes))]);
            }
        });
    }

    public function down(): void
    {
        DB::table('perfis')->orderBy('id')->each(function ($perfil) {
            $permissoes = json_decode((string) $perfil->permissoes, true);

            if (is_array($permissoes) && in_array('ordens_servico', $permissoes, true)) {
                $permissoes = array_values(array_diff($permissoes, ['ordens_servico']));
                DB::table('perfis')->where('id', $perfil->id)->update(['permissoes' => json_encode($permissoes)]);
            }
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ordem_servico_id');
        });

        Schema::dropIfExists('ordem_servico_itens');
        Schema::dropIfExists('ordens_servico');
    }
};
