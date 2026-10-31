<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protocolo de inutilização de faixa. Não altera XML nem status de nota autorizada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfe_inutilizacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('ambiente');
            $table->string('serie', 3);
            $table->char('ano', 2);
            $table->unsignedInteger('numero_inicial');
            $table->unsignedInteger('numero_final');
            $table->string('justificativa', 255);
            $table->string('situacao', 20)->default('rejeitada');
            $table->string('protocolo', 20)->nullable();
            $table->string('codigo_status', 10)->nullable();
            $table->string('motivo_status', 255)->nullable();
            $table->longText('xml')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'ambiente', 'serie'], 'nfe_inutilizacoes_serie_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_inutilizacoes');
    }
};
