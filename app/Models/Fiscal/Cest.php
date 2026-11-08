<?php

namespace App\Models\Fiscal;

use Illuminate\Database\Eloquent\Model;

class Cest extends Model
{
    protected $table = 'cests';

    protected $fillable = ['codigo', 'ncm', 'descricao', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}
