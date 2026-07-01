<?php

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
        Schema::create('grupo', function (Blueprint $table) {
            $table->id();
 
            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
 
            $table->string('nome');
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
 
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupo');
    }
};
