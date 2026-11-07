<?php

namespace App\Models\ContasReceber;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaReceberBaixa extends Model
{
    protected $table = 'conta_receber_baixas';

    protected $fillable = [
        'empresa_id',
        'conta_receber_id',
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
        return $this->belongsTo(ContaReceber::class, 'conta_receber_id');
    }
}
