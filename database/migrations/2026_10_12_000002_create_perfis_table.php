<?php

use App\Models\Empresas\Empresa;
use App\Services\Usuarios\PerfilService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('slug', 50);
            $table->string('nome', 100);
            $table->json('permissoes');
            $table->boolean('sistema')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'slug']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('perfil', 50)->default('vendedor')->change();
        });

        DB::table('users')->where('perfil', 'operador')->update(['perfil' => 'vendedor']);

        $service = app(PerfilService::class);
        foreach (Empresa::query()->get() as $empresa) {
            $service->garantir($empresa);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('perfis');
    }
};
