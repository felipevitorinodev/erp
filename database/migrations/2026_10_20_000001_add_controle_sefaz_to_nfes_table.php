<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicadas = DB::table('nfes')
            ->select('empresa_id', 'ambiente', 'serie', 'numero')
            ->groupBy('empresa_id', 'ambiente', 'serie', 'numero')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicadas->isNotEmpty()) {
            throw new RuntimeException(
                'Existem NF-e com o mesmo número/série/ambiente na mesma empresa. Resolva antes de migrar: '
                .$duplicadas->map(fn ($d) => "empresa {$d->empresa_id} amb {$d->ambiente} série {$d->serie} nº {$d->numero}")->implode('; ')
            );
        }

        Schema::table('nfes', function (Blueprint $table) {
            $table->enum('situacao', ['pendente', 'autorizada', 'rejeitada', 'denegada', 'cancelada'])
                ->default('pendente')
                ->change();
            $table->string('recibo', 20)->nullable()->after('protocolo');
            $table->longText('xml_cancelamento')->nullable()->after('xml_autorizado');
            $table->dateTime('data_cancelamento')->nullable()->after('data_autorizacao');
            $table->unique(['empresa_id', 'ambiente', 'serie', 'numero'], 'nfes_empresa_ambiente_serie_numero_unique');
        });
    }

    public function down(): void
    {
        DB::table('nfes')->where('situacao', 'denegada')->update(['situacao' => 'rejeitada']);

        Schema::table('nfes', function (Blueprint $table) {
            $table->dropUnique('nfes_empresa_ambiente_serie_numero_unique');
            $table->dropColumn(['recibo', 'xml_cancelamento', 'data_cancelamento']);
            $table->enum('situacao', ['pendente', 'autorizada', 'rejeitada', 'cancelada'])
                ->default('pendente')
                ->change();
        });
    }
};
