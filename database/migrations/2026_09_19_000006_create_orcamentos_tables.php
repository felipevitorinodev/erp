<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orcamentos', function (Blueprint $table) {
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

            $table->foreignId('venda_id')
                ->nullable()
                ->constrained('vendas')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('numero', 20)->nullable();
            $table->date('data_orcamento');
            $table->date('data_entrega')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('acrescimo', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('forma_pagamento', 100)->nullable();
            $table->text('observacoes')->nullable();

            // pendente | aprovado | recusado | cancelado
            $table->string('situacao', 20)->default('pendente');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('orcamento_itens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('orcamento_id')
                ->constrained('orcamentos')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('produto_id')
                ->nullable()
                ->constrained('produtos')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('produto_nome');
            $table->string('produto_codigo', 50)->nullable();

            $table->decimal('quantidade', 12, 3)->default(1);
            $table->decimal('preco_unitario', 12, 4)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->timestamps();
        });

        // Migra orçamentos que estavam misturados na tabela vendas
        if (Schema::hasColumn('vendas', 'tipo')) {
            $orcamentosAntigos = DB::table('vendas')
                ->where('tipo', 'orcamento')
                ->whereNull('deleted_at')
                ->get();

            foreach ($orcamentosAntigos as $antigo) {
                $novoId = DB::table('orcamentos')->insertGetId([
                    'empresa_id'      => $antigo->empresa_id,
                    'cliente_id'      => $antigo->cliente_id,
                    'usuario_id'      => $antigo->usuario_id,
                    'venda_id'        => null,
                    'numero'          => $antigo->numero,
                    'data_orcamento'  => $antigo->data_venda,
                    'data_entrega'    => $antigo->data_entrega,
                    'subtotal'        => $antigo->subtotal,
                    'desconto'        => $antigo->desconto,
                    'acrescimo'       => $antigo->acrescimo,
                    'total'           => $antigo->total,
                    'forma_pagamento' => $antigo->forma_pagamento,
                    'observacoes'     => $antigo->observacoes,
                    'situacao'        => in_array($antigo->situacao, ['pendente', 'aprovado', 'recusado', 'cancelado'], true)
                        ? $antigo->situacao
                        : 'pendente',
                    'created_at'      => $antigo->created_at,
                    'updated_at'      => $antigo->updated_at,
                    'deleted_at'      => null,
                ]);

                $itens = DB::table('venda_itens')
                    ->where('venda_id', $antigo->id)
                    ->get();

                foreach ($itens as $item) {
                    DB::table('orcamento_itens')->insert([
                        'orcamento_id'   => $novoId,
                        'produto_id'     => $item->produto_id,
                        'produto_nome'   => $item->produto_nome,
                        'produto_codigo' => $item->produto_codigo,
                        'quantidade'     => $item->quantidade,
                        'preco_unitario' => $item->preco_unitario,
                        'desconto'       => $item->desconto,
                        'total'          => $item->total,
                        'created_at'     => $item->created_at,
                        'updated_at'     => $item->updated_at,
                    ]);
                }

                DB::table('venda_itens')->where('venda_id', $antigo->id)->delete();
                DB::table('vendas')->where('id', $antigo->id)->delete();
            }

            Schema::table('vendas', function (Blueprint $table) {
                $table->dropColumn('tipo');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('vendas', 'tipo')) {
            Schema::table('vendas', function (Blueprint $table) {
                $table->string('tipo', 20)->default('venda')->after('situacao');
            });
        }

        Schema::dropIfExists('orcamento_itens');
        Schema::dropIfExists('orcamentos');
    }
};
