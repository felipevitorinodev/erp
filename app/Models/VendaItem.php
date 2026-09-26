<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendaItem extends Model
{
    protected $table = 'venda_itens';

    protected $fillable = [
        'venda_id',
        'produto_id',
        'produto_nome',
        'produto_codigo',
        'quantidade',
        'preco_unitario',
        'desconto',
        'total',
    ];

    protected $casts = [
        'quantidade'     => 'decimal:2',
        'preco_unitario' => 'decimal:2',
        'desconto'       => 'decimal:2',
        'total'          => 'decimal:2',
    ];

    public function venda()
    {
        return $this->belongsTo(Venda::class);
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}
