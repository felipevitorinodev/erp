<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conta_receber_baixas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('conta_receber_id')->constrained('contas_receber')->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('valor', 12, 2);
            $table->date('data_pagamento');
            $table->string('forma_pagamento', 100)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'data_pagamento']);
        });

        Schema::create('conta_pagar_baixas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('conta_pagar_id')->constrained('contas_pagar')->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('valor', 12, 2);
            $table->date('data_pagamento');
            $table->string('forma_pagamento', 100)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'data_pagamento']);
        });

        $agora = now();

        foreach (DB::table('contas_receber')->whereIn('situacao', ['paga', 'parcial'])->where('valor_pago', '>', 0)->whereNotNull('data_pagamento')->whereNull('deleted_at')->get() as $conta) {
            DB::table('conta_receber_baixas')->insert([
                'empresa_id' => $conta->empresa_id,
                'conta_receber_id' => $conta->id,
                'valor' => $conta->valor_pago,
                'data_pagamento' => $conta->data_pagamento,
                'forma_pagamento' => $conta->forma_pagamento,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }

        foreach (DB::table('contas_pagar')->whereIn('situacao', ['paga', 'parcial'])->where('valor_pago', '>', 0)->whereNotNull('data_pagamento')->whereNull('deleted_at')->get() as $conta) {
            DB::table('conta_pagar_baixas')->insert([
                'empresa_id' => $conta->empresa_id,
                'conta_pagar_id' => $conta->id,
                'valor' => $conta->valor_pago,
                'data_pagamento' => $conta->data_pagamento,
                'forma_pagamento' => $conta->forma_pagamento,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conta_receber_baixas');
        Schema::dropIfExists('conta_pagar_baixas');
    }
};
