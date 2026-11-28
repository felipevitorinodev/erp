<?php

namespace App\Models\Fiscal;

use Illuminate\Database\Eloquent\Model;

class NfseServico extends Model
{
    protected $table = 'nfse_servicos';

    protected $fillable = ['codigo', 'item', 'descricao', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
