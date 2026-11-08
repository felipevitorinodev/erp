<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Código interno do produto único por empresa. Conta os excluídos, que continuam nas vendas antigas.
 *
 * Se já houver código repetido, o índice NÃO é criado e fica só um aviso no log, para o `migrate` não parar
 * no meio (nem travar uma subida automática, se o deploy por container voltar). Depois de corrigir os
 * códigos (SQL 4.2 de docs/revisao/10-revisao-2026-11-07.md), crie o índice com o SQL 4.5.
 */
return new class extends Migration
{
    private const INDICE = 'produtos_empresa_codigo_unique';

    private const INDICE_EMPRESA = 'produtos_empresa_id_index';

    public function up(): void
    {
        if (Schema::hasIndex('produtos', self::INDICE)) {
            return;
        }

        $repetidos = DB::table('produtos')
            ->whereNotNull('codigo')
            ->select('empresa_id', 'codigo')
            ->groupBy('empresa_id', 'codigo')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($repetidos > 0) {
            Log::warning('Índice único de código de produto não criado: há códigos repetidos na mesma empresa.', [
                'codigos_repetidos' => $repetidos,
                'orientacao' => 'Corrija pelo SQL 4.2 e crie o índice pelo SQL 4.5 de docs/revisao/10-revisao-2026-11-07.md.',
            ]);

            return;
        }

        Schema::table('produtos', function (Blueprint $table) {
            $table->unique(['empresa_id', 'codigo'], self::INDICE);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('produtos', self::INDICE)) {
            return;
        }

        // O MySQL pode ter descartado o índice implícito da chave estrangeira de empresa_id ao criar o
        // índice composto. Sem outro índice começando por empresa_id, o DROP seria recusado (erro 1553).
        $outroIndiceDaEmpresa = collect(Schema::getIndexes('produtos'))->contains(
            fn (array $indice) => $indice['name'] !== self::INDICE && ($indice['columns'][0] ?? null) === 'empresa_id'
        );

        Schema::table('produtos', function (Blueprint $table) use ($outroIndiceDaEmpresa) {
            if (! $outroIndiceDaEmpresa) {
                $table->index('empresa_id', self::INDICE_EMPRESA);
            }

            $table->dropUnique(self::INDICE);
        });
    }
};
