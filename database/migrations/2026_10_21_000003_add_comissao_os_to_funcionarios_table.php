<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcionarios', function (Blueprint $table) {
            $table->decimal('comissao_os_percentual', 5, 2)->default(0)->after('percentual_comissao');
            // total | servicos
            $table->string('comissao_os_base', 10)->default('total')->after('comissao_os_percentual');
        });

        // Mantém para quem já existe o mesmo percentual usado nas vendas; a base pode ser ajustada no cadastro.
        DB::table('funcionarios')
            ->whereNotNull('percentual_comissao')
            ->update(['comissao_os_percentual' => DB::raw('percentual_comissao')]);

        Schema::table('comissoes', function (Blueprint $table) {
            $table->decimal('base_calculo', 12, 2)->nullable()->after('percentual');
        });
    }

    public function down(): void
    {
        Schema::table('comissoes', function (Blueprint $table) {
            $table->dropColumn('base_calculo');
        });

        Schema::table('funcionarios', function (Blueprint $table) {
            $table->dropColumn(['comissao_os_percentual', 'comissao_os_base']);
        });
    }
};
