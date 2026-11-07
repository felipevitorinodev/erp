<?php

namespace App\Models\ContasPagar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaPagarBaixa extends Model
{
    protected $table = 'conta_pagar_baixas';

    protected $fillable = [
        'empresa_id',
        'conta_pagar_id',
        'valor',
        'data_pagamento',
        'forma_pagamento',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_pagamento' => 'date',
    ];

    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class, 'conta_pagar_id');
    }
}
