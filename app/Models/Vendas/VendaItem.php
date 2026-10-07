<?php

namespace App\Models\Vendas;

use App\Models\Produtos\Produto;
use Illuminate\Database\Eloquent\Model;

class VendaItem extends Model
{
    protected $table = 'venda_itens';

    protected $fillable = [
        'venda_id',
        'produto_id',
        'produto_nome',
        'produto_codigo',
        'ncm',
        'cest',
        'cfop',
        'unidade',
        'origem',
        'csosn',
        'cst_pis',
        'cst_cofins',
        'ean',
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
        'origem'         => 'integer',
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
