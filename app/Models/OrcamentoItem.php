<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrcamentoItem extends Model
{
    protected $table = 'orcamento_itens';

    protected $fillable = [
        'orcamento_id',
        'produto_id',
        'produto_nome',
        'produto_codigo',
        'quantidade',
        'preco_unitario',
        'desconto',
        'total',
    ];

    protected $casts = [
        'quantidade'     => 'decimal:3',
        'preco_unitario' => 'decimal:4',
        'desconto'       => 'decimal:2',
        'total'          => 'decimal:2',
    ];

    public function orcamento()
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}
