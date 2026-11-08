<?php

namespace App\Models\Fiscal;

use Illuminate\Database\Eloquent\Model;

class Cfop extends Model
{
    protected $table = 'cfops';

    protected $fillable = ['codigo', 'descricao', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
