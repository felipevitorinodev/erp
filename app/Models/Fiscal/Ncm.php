<?php

namespace App\Models\Fiscal;

use Illuminate\Database\Eloquent\Model;

class Ncm extends Model
{
    protected $table = 'ncms';

    protected $fillable = ['codigo', 'descricao', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
