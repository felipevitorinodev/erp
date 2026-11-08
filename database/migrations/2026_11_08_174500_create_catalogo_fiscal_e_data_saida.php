<?php

use App\Support\CatalogoFiscal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->date('data_saida')->nullable()->after('data_venda');
        });

        Schema::create('cfops', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 4)->unique();
            $table->string('descricao', 255);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('ncms', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 8)->unique();
            $table->string('descricao', 180);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('cests', function (Blueprint $table) {
            $table->id();
            $table->char('codigo', 7)->unique();
            $table->char('ncm', 8)->nullable();
            $table->string('descricao', 180);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        CatalogoFiscal::sincronizar();
    }

    public function down(): void
    {
        Schema::dropIfExists('cests');
        Schema::dropIfExists('ncms');
        Schema::dropIfExists('cfops');

        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('data_saida');
        });
    }
};
