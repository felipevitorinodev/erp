<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntradaEstoqueItem extends Model
{
    protected $table = 'entrada_estoque_itens';

    protected $fillable = [
        'entrada_estoque_id',
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

    public function entrada()
    {
        return $this->belongsTo(EntradaEstoque::class, 'entrada_estoque_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}
