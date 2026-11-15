<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carta de Correção (evento 110110) registrada na SEFAZ. Só entra aqui o evento aceito (135/136);
 * a recusa volta como mensagem e não ocupa sequência. Não altera a tabela nfes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfe_cartas_correcao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('nfe_id')
                ->constrained('nfes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedTinyInteger('sequencia');
            $table->text('correcao');
            $table->string('protocolo', 20)->nullable();
            $table->string('codigo_status', 10)->nullable();
            $table->string('motivo_status', 255)->nullable();
            $table->dateTime('data_registro')->nullable();
            $table->longText('xml')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['nfe_id', 'sequencia'], 'nfe_cartas_correcao_sequencia_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_cartas_correcao');
    }
};
