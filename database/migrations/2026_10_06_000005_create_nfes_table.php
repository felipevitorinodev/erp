<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nfes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('venda_id')
                ->nullable()
                ->constrained('vendas')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('numero', 9);
            $table->string('serie', 3);
            $table->string('chave_acesso', 44)->nullable()->unique();
            $table->enum('situacao', ['pendente', 'autorizada', 'rejeitada', 'cancelada'])->default('pendente');
            $table->unsignedTinyInteger('ambiente');
            $table->longText('xml_assinado')->nullable();
            $table->longText('xml_autorizado')->nullable();
            $table->string('protocolo', 20)->nullable();
            $table->string('codigo_status', 10)->nullable();
            $table->string('motivo_status', 255)->nullable();
            $table->dateTime('data_autorizacao')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'situacao']);
            $table->index(['empresa_id', 'venda_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nfes');
    }
};
