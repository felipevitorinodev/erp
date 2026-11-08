<?php

namespace App\Models\Empresas;

use App\Enums\TipoPessoa;
use App\Models\Clientes\Cliente;
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
        'nfce_serie',
        'nfce_emitir_pdv',
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

    protected $hidden = [
        'certificado_senha',
        'nfe_csc',
        'nfce_csc_homologacao',
        'nfce_csc_producao',
    ];

    protected $casts = [
        'tipo_pessoa' => TipoPessoa::class,
        'ativo' => 'boolean',
        'nfe_ambiente' => 'integer',
        'nfe_sequencia' => 'integer',
        'crt' => 'integer',
        'nfe_destino' => 'integer',
        'nfce_emitir_pdv' => 'boolean',
    ];

    /**
     * Campos que não podem voltar para a sessão nem para a tela.
     *
     * @return list<string>
     */
    public static function segredosForaDaSessao(): array
    {
        return [
            'current_password',
            'password',
            'password_confirmation',
            'certificado_senha',
            'certificado_pfx',
            'nfe_csc',
            'nfce_csc_homologacao',
            'nfce_csc_producao',
        ];
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }

    public function getCertificadoSenhaDecryptedAttribute(): ?string
    {
        return $this->descriptografarSegredo($this->certificado_senha);
    }

    public function getNfceCscHomologacaoDecryptedAttribute(): ?string
    {
        return $this->descriptografarSegredo($this->nfce_csc_homologacao);
    }

    public function getNfceCscProducaoDecryptedAttribute(): ?string
    {
        return $this->descriptografarSegredo($this->nfce_csc_producao);
    }

    private function descriptografarSegredo(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Crypt::decryptString($valor);
        } catch (Throwable) {
            return null;
        }
    }
}
