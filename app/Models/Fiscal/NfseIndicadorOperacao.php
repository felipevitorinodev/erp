<?php

namespace App\Models\Fiscal;

use Illuminate\Database\Eloquent\Model;

class NfseIndicadorOperacao extends Model
{
    protected $table = 'nfse_indicadores_operacao';

    protected $fillable = ['codigo', 'descricao', 'local', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
