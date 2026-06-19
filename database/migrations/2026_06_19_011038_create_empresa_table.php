<?php

use App\Enums\TipoPessoa;
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
        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social', 150);
            $table->string('nome_fantasia', 150)->nullable();
            $table->unsignedTinyInteger('tipo_pessoa')->default(TipoPessoa::PJ->value);
            $table->string('cnpj', 18)->nullable()->unique();
            $table->string('cpf', 14)->nullable();
            $table->string('ie', 20)->nullable();
            $table->string('im', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('logradouro', 150)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->char('uf', 2)->nullable();
            $table->unsignedTinyInteger('ativo')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa');
    }
};
