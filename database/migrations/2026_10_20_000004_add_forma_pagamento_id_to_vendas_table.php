<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Até aqui a venda guardava só o nome da forma de pagamento; renomear/desativar a forma fazia a venda
 * a prazo ser confirmada sem conta a receber. O nome continua gravado (NF-e e telas usam).
 * Backfill: casa pelo nome (sem diferenciar maiúsculas) dentro da empresa, preferindo formas ativas e não excluídas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->foreignId('forma_pagamento_id')
                ->nullable()
                ->after('forma_pagamento')
                ->constrained('formas_pagamento')
                ->nullOnDelete();
        });

        $formas = DB::table('formas_pagamento')
            ->orderByRaw('CASE WHEN deleted_at IS NULL AND ativo = 1 THEN 0 WHEN deleted_at IS NULL THEN 1 ELSE 2 END')
            ->orderBy('id')
            ->get(['id', 'empresa_id', 'nome']);

        $porNome = [];
        foreach ($formas as $forma) {
            $chave = $forma->empresa_id . '|' . mb_strtolower(trim((string) $forma->nome));
            $porNome[$chave] ??= $forma->id;
        }

        DB::table('vendas')
            ->whereNotNull('forma_pagamento')
            ->where('forma_pagamento', '<>', '')
            ->orderBy('id')
            ->select(['id', 'empresa_id', 'forma_pagamento'])
            ->chunkById(500, function ($vendas) use ($porNome) {
                foreach ($vendas as $venda) {
                    $id = $porNome[$venda->empresa_id . '|' . mb_strtolower(trim((string) $venda->forma_pagamento))] ?? null;

                    if ($id) {
                        DB::table('vendas')->where('id', $venda->id)->update(['forma_pagamento_id' => $id]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forma_pagamento_id');
        });
    }
};
