<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Comissao extends Model
{
    use HasFactory;

    protected $table = 'comissoes';

    protected $fillable = [
        'empresa_id',
        'venda_id',
        'funcionario_id',
        'percentual',
        'valor',
        'situacao',
        'data_pagamento',
    ];

    protected $casts = [
        'percentual'      => 'decimal:2',
        'valor'           => 'decimal:2',
        'data_pagamento'  => 'date',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function venda()
    {
        return $this->belongsTo(Venda::class);
    }

    public function funcionario()
    {
        return $this->belongsTo(Funcionario::class);
    }
}
