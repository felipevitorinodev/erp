<?php

namespace App\Models\Fiscal;

use Illuminate\Database\Eloquent\Model;

class NfseClassificacao extends Model
{
    protected $table = 'nfse_classificacoes';

    protected $fillable = ['codigo', 'cst', 'cst_descricao', 'descricao', 'exige_tributacao', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
        'exige_tributacao' => 'boolean',
    ];
}
