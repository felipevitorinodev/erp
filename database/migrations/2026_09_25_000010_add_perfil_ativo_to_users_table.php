<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('perfil', 20)->default('operador')->after('email');
            $table->boolean('ativo')->default(true)->after('perfil');
        });

        // Usuários já existentes passam a admin para não perder acesso
        DB::table('users')->update(['perfil' => 'admin', 'ativo' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['perfil', 'ativo']);
        });
    }
};
