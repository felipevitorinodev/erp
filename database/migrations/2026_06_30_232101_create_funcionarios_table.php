<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funcionarios', function (Blueprint $table) {
            $table->id();
 
            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
 
            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
 
            $table->string('nome');
            $table->string('cpf', 14)->nullable();
            $table->string('rg', 20)->nullable();
            $table->date('data_nascimento')->nullable();
 
            $table->string('email')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('celular', 20)->nullable();
 
            $table->string('cargo', 100)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->decimal('salario', 12, 2)->nullable();
 
            $table->date('data_admissao')->nullable();
            $table->date('data_demissao')->nullable();
            $table->string('tipo_contrato', 50)->nullable();
 
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
 
            $table->timestamps();
            $table->softDeletes();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('funcionarios');
    }
};
