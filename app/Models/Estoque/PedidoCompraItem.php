<?php

namespace App\Models\Estoque;

use App\Models\Produtos\Produto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoCompraItem extends Model
{
    use HasFactory;

    protected $table = 'pedido_compra_itens';

    protected $fillable = [
        'pedido_compra_id',
        'produto_id',
        'produto_nome',
        'produto_codigo',
        'quantidade',
        'quantidade_recebida',
        'preco_unitario',
        'desconto',
        'total',
    ];

    protected $casts = [
        'quantidade' => 'decimal:3',
        'quantidade_recebida' => 'decimal:3',
        'preco_unitario' => 'decimal:2',
        'desconto' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function pedidoCompra()
    {
        return $this->belongsTo(PedidoCompra::class);
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}
