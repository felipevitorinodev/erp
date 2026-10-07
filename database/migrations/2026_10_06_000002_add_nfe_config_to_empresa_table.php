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
        Schema::table('empresa', function (Blueprint $table) {
            $table->unsignedTinyInteger('nfe_ambiente')->default(2)->after('certificado_senha');
            $table->string('nfe_serie', 3)->default('001')->after('nfe_ambiente');
            $table->unsignedBigInteger('nfe_sequencia')->default(1)->after('nfe_serie');
            $table->string('nfe_csc', 36)->nullable()->after('nfe_sequencia');
            $table->string('nfe_id_csc', 6)->nullable()->after('nfe_csc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn([
                'nfe_ambiente',
                'nfe_serie',
                'nfe_sequencia',
                'nfe_csc',
                'nfe_id_csc',
            ]);
        });
    }
};
