<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa a numeração da NFC-e (modelo 65) da NF-e (modelo 55).
 *
 * Notas já gravadas recebem modelo 55 pelo default da coluna nova.
 * XML, situação, chave, protocolo e número não são atualizados.
 *
 * No MySQL 5.7 o índice único atual de nfe_numeracoes começa com empresa_id
 * e é o único que sustenta a chave estrangeira. O índice novo é criado antes
 * de remover o antigo, senão o ALTER pode falhar com o erro 1553.
 * A mesma ordem vale para nfes.
 *
 * O down() confere as três tabelas e os campos de CSC antes de qualquer DDL,
 * porque o MySQL confirma cada ALTER na hora e não desfaz o anterior.
 * A série 001 é provisória: o índice inclui o modelo, então ela não colide
 * com a NF-e, mas a série definitiva depende do contador.
 *
 * No MySQL 5.7, acrescentar a coluna com default reescreve a tabela.
 * Medir o tamanho de nfes e rodar fora do horário de pico.
 * Esta migration não emite NFC-e e não grava CSC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfes', function (Blueprint $table) {
            $table->unsignedTinyInteger('modelo')->default(55)->after('venda_id');
        });

        Schema::table('nfes', function (Blueprint $table) {
            $table->unique(
                ['empresa_id', 'modelo', 'ambiente', 'serie', 'numero'],
                'nfes_empresa_modelo_ambiente_serie_numero_unique'
            );
        });

        Schema::table('nfes', function (Blueprint $table) {
            $table->dropUnique('nfes_empresa_ambiente_serie_numero_unique');
        });

        Schema::table('nfe_numeracoes', function (Blueprint $table) {
            $table->unsignedTinyInteger('modelo')->default(55)->after('empresa_id');
        });

        Schema::table('nfe_numeracoes', function (Blueprint $table) {
            $table->unique(
                ['empresa_id', 'modelo', 'ambiente', 'serie'],
                'nfe_numeracoes_modelo_unica'
            );
        });

        Schema::table('nfe_numeracoes', function (Blueprint $table) {
            $table->dropUnique('nfe_numeracoes_unica');
        });

        Schema::table('nfe_inutilizacoes', function (Blueprint $table) {
            $table->unsignedTinyInteger('modelo')->default(55)->after('empresa_id');
        });

        Schema::table('empresa', function (Blueprint $table) {
            $table->string('nfce_serie', 3)->default('001')->after('nfe_sequencia');
            $table->text('nfce_csc_homologacao')->nullable()->after('nfce_serie');
            $table->string('nfce_id_csc_homologacao', 6)->nullable()->after('nfce_csc_homologacao');
            $table->text('nfce_csc_producao')->nullable()->after('nfce_id_csc_homologacao');
            $table->string('nfce_id_csc_producao', 6)->nullable()->after('nfce_csc_producao');
        });
    }

    public function down(): void
    {
        $this->abortarSeHouverModeloDiferenteDe55();
        $this->abortarSeHouverCsc();

        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn([
                'nfce_serie',
                'nfce_csc_homologacao',
                'nfce_id_csc_homologacao',
                'nfce_csc_producao',
                'nfce_id_csc_producao',
            ]);
        });

        Schema::table('nfe_inutilizacoes', function (Blueprint $table) {
            $table->dropColumn('modelo');
        });

        Schema::table('nfe_numeracoes', function (Blueprint $table) {
            $table->unique(['empresa_id', 'ambiente', 'serie'], 'nfe_numeracoes_unica');
        });

        Schema::table('nfe_numeracoes', function (Blueprint $table) {
            $table->dropUnique('nfe_numeracoes_modelo_unica');
        });

        Schema::table('nfe_numeracoes', function (Blueprint $table) {
            $table->dropColumn('modelo');
        });

        Schema::table('nfes', function (Blueprint $table) {
            $table->unique(
                ['empresa_id', 'ambiente', 'serie', 'numero'],
                'nfes_empresa_ambiente_serie_numero_unique'
            );
        });

        Schema::table('nfes', function (Blueprint $table) {
            $table->dropUnique('nfes_empresa_modelo_ambiente_serie_numero_unique');
        });

        Schema::table('nfes', function (Blueprint $table) {
            $table->dropColumn('modelo');
        });
    }

    private function abortarSeHouverModeloDiferenteDe55(): void
    {
        foreach (['nfes', 'nfe_numeracoes', 'nfe_inutilizacoes'] as $tabela) {
            if (DB::table($tabela)->where('modelo', '<>', 55)->exists()) {
                throw new RuntimeException("Existe modelo diferente de 55 em {$tabela}. Reversão interrompida.");
            }
        }
    }

    private function abortarSeHouverCsc(): void
    {
        $campos = [
            'nfce_csc_homologacao',
            'nfce_id_csc_homologacao',
            'nfce_csc_producao',
            'nfce_id_csc_producao',
        ];

        $preenchido = DB::table('empresa')->where(function ($query) use ($campos) {
            foreach ($campos as $campo) {
                $query->orWhere(function ($interno) use ($campo) {
                    $interno->whereNotNull($campo)->where($campo, '<>', '');
                });
            }
        })->exists();

        if ($preenchido) {
            throw new RuntimeException(
                'Há CSC ou idCSC de NFC-e gravado na empresa. A reversão foi interrompida para não apagar esse dado. Faça o backup do banco antes de uma reversão deliberada.'
            );
        }
    }
};
