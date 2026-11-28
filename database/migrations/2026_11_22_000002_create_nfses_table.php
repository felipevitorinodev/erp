<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NFS-e Padrão Nacional: notas, numeração da DPS, configuração na empresa e códigos de serviço
 * memorizados no produto (autocompletar da próxima emissão) e retenção de ISS memorizada no cliente.
 * Só acrescenta tabelas e colunas anuláveis ou com valor padrão: nada existente é alterado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('venda_id')
                ->nullable()
                ->constrained('vendas')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('serie', 5);
            $table->unsignedBigInteger('numero');
            $table->string('id_dps', 45)->nullable();
            $table->string('numero_nfse', 13)->nullable();
            $table->string('chave_acesso', 50)->nullable()->unique();
            $table->string('situacao', 20)->default('pendente');
            $table->unsignedTinyInteger('ambiente');
            $table->string('leiaute', 10)->nullable();

            $table->char('c_trib_nac', 6);
            $table->char('c_trib_mun', 3)->nullable();
            $table->char('nbs', 9)->nullable();
            $table->char('cst_ibs_cbs', 3)->nullable();
            $table->char('class_trib', 6)->nullable();
            $table->char('ind_op', 6)->nullable();
            $table->char('municipio_prestacao', 7)->nullable();
            $table->text('descricao');

            $table->decimal('valor_servico', 15, 2)->default(0);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->decimal('valor_liquido', 15, 2)->default(0);
            $table->boolean('iss_retido')->default(false);
            $table->decimal('aliquota_iss', 5, 2)->nullable();
            $table->decimal('valor_iss', 15, 2)->nullable();

            $table->date('data_competencia')->nullable();
            $table->dateTime('data_emissao')->nullable();
            $table->longText('xml_assinado')->nullable();
            $table->longText('xml_nfse')->nullable();
            $table->longText('xml_cancelamento')->nullable();
            $table->string('codigo_status', 10)->nullable();
            $table->string('motivo_status', 255)->nullable();
            $table->text('mensagens')->nullable();
            $table->dateTime('data_autorizacao')->nullable();
            $table->dateTime('data_cancelamento')->nullable();
            $table->string('motivo_cancelamento', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'ambiente', 'serie', 'numero']);
            $table->index(['empresa_id', 'situacao']);
            $table->index(['empresa_id', 'venda_id']);
        });

        Schema::create('nfse_numeracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('ambiente');
            $table->string('serie', 5);
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->unique(['empresa_id', 'ambiente', 'serie']);
        });

        Schema::table('empresa', function (Blueprint $table) {
            $table->unsignedTinyInteger('nfse_ambiente')->default(2);
            $table->string('nfse_serie', 5)->nullable();
            $table->unsignedTinyInteger('nfse_regime_apuracao_sn')->nullable();
            $table->unsignedTinyInteger('nfse_regime_especial')->default(0);
            $table->decimal('nfse_aliquota_iss', 5, 2)->nullable();
            $table->decimal('nfse_ptot_trib_sn', 5, 2)->nullable();
            $table->decimal('nfse_ptot_trib_fed', 5, 2)->nullable();
            $table->decimal('nfse_ptot_trib_est', 5, 2)->nullable();
            $table->decimal('nfse_ptot_trib_mun', 5, 2)->nullable();
            $table->char('nfse_c_trib_nac', 6)->nullable();
            $table->char('nfse_c_trib_mun', 3)->nullable();
            $table->char('nfse_nbs', 9)->nullable();
            $table->char('nfse_class_trib', 6)->nullable();
            $table->char('nfse_ind_op', 6)->nullable();
            $table->string('nfse_convenio_situacao', 20)->nullable();
            $table->dateTime('nfse_convenio_consultado_em')->nullable();
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->char('nfse_c_trib_nac', 6)->nullable();
            $table->char('nfse_c_trib_mun', 3)->nullable();
            $table->char('nfse_nbs', 9)->nullable();
            $table->char('nfse_class_trib', 6)->nullable();
            $table->char('nfse_ind_op', 6)->nullable();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('nfse_iss_retido')->default(false);
        });

        // Quem já emite NF-e passa a ver a NFS-e (admin já tem tudo).
        DB::table('perfis')->orderBy('id')->each(function ($perfil) {
            $permissoes = json_decode((string) $perfil->permissoes, true);

            if (is_array($permissoes) && in_array('nfe', $permissoes, true) && ! in_array('nfse', $permissoes, true)) {
                $permissoes[] = 'nfse';
                DB::table('perfis')->where('id', $perfil->id)->update(['permissoes' => json_encode(array_values($permissoes))]);
            }
        });
    }

    public function down(): void
    {
        DB::table('perfis')->orderBy('id')->each(function ($perfil) {
            $permissoes = json_decode((string) $perfil->permissoes, true);

            if (is_array($permissoes) && in_array('nfse', $permissoes, true)) {
                $permissoes = array_values(array_diff($permissoes, ['nfse']));
                DB::table('perfis')->where('id', $perfil->id)->update(['permissoes' => json_encode($permissoes)]);
            }
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('nfse_iss_retido');
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['nfse_c_trib_nac', 'nfse_c_trib_mun', 'nfse_nbs', 'nfse_class_trib', 'nfse_ind_op']);
        });

        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn([
                'nfse_ambiente', 'nfse_serie', 'nfse_regime_apuracao_sn', 'nfse_regime_especial', 'nfse_aliquota_iss',
                'nfse_ptot_trib_sn', 'nfse_ptot_trib_fed', 'nfse_ptot_trib_est', 'nfse_ptot_trib_mun',
                'nfse_c_trib_nac', 'nfse_c_trib_mun', 'nfse_nbs', 'nfse_class_trib', 'nfse_ind_op',
                'nfse_convenio_situacao', 'nfse_convenio_consultado_em',
            ]);
        });

        Schema::dropIfExists('nfse_numeracoes');
        Schema::dropIfExists('nfses');
    }
};
