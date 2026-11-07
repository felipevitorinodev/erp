<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Produtos sem código interno recebem uma sequência numérica por empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $empresas = DB::table('produtos')
            ->where(function ($query) {
                $query->whereNull('codigo')->orWhere('codigo', '');
            })
            ->distinct()
            ->pluck('empresa_id');

        foreach ($empresas as $empresaId) {
            $ultimo = DB::table('produtos')
                ->where('empresa_id', $empresaId)
                ->whereRaw("codigo REGEXP '^[0-9]+$'")
                ->max(DB::raw('CAST(codigo AS UNSIGNED)'));
            $proximo = $ultimo ? ((int) $ultimo) + 1 : 1;

            $produtos = DB::table('produtos')
                ->where('empresa_id', $empresaId)
                ->where(function ($query) {
                    $query->whereNull('codigo')->orWhere('codigo', '');
                })
                ->orderBy('id')
                ->pluck('id');

            foreach ($produtos as $id) {
                DB::table('produtos')->where('id', $id)->update([
                    'codigo' => str_pad((string) $proximo, 6, '0', STR_PAD_LEFT),
                ]);
                $proximo++;
            }
        }
    }

    public function down(): void
    {
        //
    }
};
