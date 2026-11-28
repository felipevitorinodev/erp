<?php

namespace App\Models\Fiscal;

use Illuminate\Database\Eloquent\Model;

class NfseNbs extends Model
{
    protected $table = 'nfse_nbs';

    protected $fillable = ['codigo', 'descricao', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
