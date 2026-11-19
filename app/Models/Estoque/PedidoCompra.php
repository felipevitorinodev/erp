<?php

namespace App\Models\Estoque;

use App\Models\Empresas\Empresa;
use App\Models\Fornecedores\Fornecedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoCompra extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pedidos_compra';

    protected $fillable = [
        'empresa_id',
        'fornecedor_id',
        'numero_seq',
        'numero',
        'data_emissao',
        'data_prevista',
        'subtotal',
        'desconto',
        'total',
        'observacoes',
        'situacao',
    ];

    protected $casts = [
        'data_emissao' => 'date',
        'data_prevista' => 'date',
        'subtotal' => 'decimal:2',
        'desconto' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function itens()
    {
        return $this->hasMany(PedidoCompraItem::class);
    }

    public function entradasEstoque()
    {
        return $this->hasMany(EntradaEstoque::class, 'pedido_compra_id');
    }
}
