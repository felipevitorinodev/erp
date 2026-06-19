<?php

namespace App\Models;

use App\Enums\TipoPessoa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

    protected $casts = [
        'tipo_pessoa' => TipoPessoa::class,
        'ativo'       => 'boolean',
    ];
}