<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContaPagar extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'contas_pagar';

    protected $fillable = [
        'empresa_id',
        'fornecedor_id',
        'descricao',
        'valor',
        'valor_pago',
        'data_vencimento',
        'data_pagamento',
        'forma_pagamento',
        'situacao',
        'observacoes',
    ];

    protected $casts = [
        'valor'           => 'decimal:2',
        'valor_pago'      => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento'  => 'date',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class);
    }
}
