<?php

namespace App\Models\Empresas;

use App\Models\Clientes\Cliente;
use App\Enums\TipoPessoa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class Empresa extends Model
{
    use SoftDeletes;

    protected $table = 'empresa';

    protected $fillable = [
        'razao_social',
        'nome_fantasia',
        'tipo_pessoa',
        'cnpj',
        'cpf',
        'ie',
        'im',
        'email',
        'telefone',
        'celular',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'ativo',
        'certificado_path',
        'certificado_senha',
        'nfe_ambiente',
        'nfe_serie',
        'nfe_sequencia',
        'nfe_csc',
        'nfe_id_csc',
        'cod_ibge',
        'crt',
        'nfe_destino',
        'nfe_cfop_padrao',
        'nfe_csosn_padrao',
        'nfe_cst_pis_padrao',
        'nfe_cst_cofins_padrao',
    ];

    protected $casts = [
        'tipo_pessoa' => TipoPessoa::class,
        'ativo'       => 'boolean',
        'nfe_ambiente' => 'integer',
        'nfe_sequencia' => 'integer',
        'crt' => 'integer',
        'nfe_destino' => 'integer',
    ];

    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }

    public function getCertificadoSenhaDecryptedAttribute(): ?string
    {
        if ($this->certificado_senha === null || $this->certificado_senha === '') {
            return null;
        }

        try {
            return Crypt::decryptString($this->certificado_senha);
        } catch (Throwable) {
            return null;
        }
    }
}