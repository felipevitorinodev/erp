<?php

namespace App\Models\Nfes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Carta de Correção (CC-e, evento 110110) registrada na SEFAZ. A de maior sequência substitui as anteriores.
 */
class NfeCartaCorrecao extends Model
{
    protected $table = 'nfe_cartas_correcao';

    protected $fillable = [
        'empresa_id',
        'nfe_id',
        'sequencia',
        'correcao',
        'protocolo',
        'codigo_status',
        'motivo_status',
        'data_registro',
        'xml',
        'usuario_id',
    ];

    protected $casts = [
        'sequencia' => 'integer',
        'data_registro' => 'datetime',
    ];

    public function nfe(): BelongsTo
    {
        return $this->belongsTo(Nfe::class);
    }
}
