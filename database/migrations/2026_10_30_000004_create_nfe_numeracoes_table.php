<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uma sequência por empresa, ambiente e série.
 * Cada ambiente fica com o maior número já gravado nas notas daquela série.
 * No ambiente em uso, o contador antigo também entra: ultimo = max(maior nota, nfe_sequencia - 1).
 * Assim um número reservado e não gravado não volta a ser emitido.
 * O outro ambiente não herda esse contador. Produção sem nota começa em 1.
 * O down() grava em nfe_sequencia o maior número usado em qualquer ambiente, mais um, e só então apaga a tabela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfe_numeracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('ambiente');
            $table->string('serie', 3);
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->unique(['empresa_id', 'ambiente', 'serie'], 'nfe_numeracoes_unica');
        });

        $agora = now();

        DB::table('empresa')->orderBy('id')->select('id', 'nfe_ambiente', 'nfe_serie', 'nfe_sequencia')->each(function ($empresa) use ($agora) {
            $serie = $empresa->nfe_serie ?: '001';
            $ambienteAtual = (int) ($empresa->nfe_ambiente ?: 2);
            $proximoAtual = 1;

            foreach ([1, 2] as $ambiente) {
                $ultimo = (int) DB::table('nfes')
                    ->where('empresa_id', $empresa->id)
                    ->where('ambiente', $ambiente)
                    ->where('serie', $serie)
                    ->max('numero');

                if ($ambiente === $ambienteAtual) {
                    $ultimo = max($ultimo, max(0, (int) $empresa->nfe_sequencia - 1));
                }

                DB::table('nfe_numeracoes')->insert([
                    'empresa_id' => $empresa->id,
                    'ambiente' => $ambiente,
                    'serie' => $serie,
                    'ultimo_numero' => $ultimo,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);

                if ($ambiente === $ambienteAtual) {
                    $proximoAtual = $ultimo + 1;
                }
            }

            DB::table('empresa')->where('id', $empresa->id)->update([
                'nfe_sequencia' => $proximoAtual,
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('nfe_numeracoes')) {
            $maiores = DB::table('nfe_numeracoes')
                ->select('empresa_id', DB::raw('MAX(ultimo_numero) as ultimo'))
                ->groupBy('empresa_id')
                ->get();

            foreach ($maiores as $linha) {
                $maiorNota = (int) DB::table('nfes')
                    ->where('empresa_id', $linha->empresa_id)
                    ->max('numero');

                DB::table('empresa')->where('id', $linha->empresa_id)->update([
                    'nfe_sequencia' => max((int) $linha->ultimo, $maiorNota) + 1,
                ]);
            }
        }

        Schema::dropIfExists('nfe_numeracoes');
    }
};
