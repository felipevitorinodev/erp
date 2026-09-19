<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Orcamento extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'orcamentos';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'usuario_id',
        'venda_id',
        'numero',
        'data_orcamento',
        'data_entrega',
        'subtotal',
        'desconto',
        'acrescimo',
        'total',
        'forma_pagamento',
        'observacoes',
        'situacao',
    ];

    protected $casts = [
        'data_orcamento' => 'date',
        'data_entrega'   => 'date',
        'subtotal'       => 'decimal:2',
        'desconto'       => 'decimal:2',
        'acrescimo'      => 'decimal:2',
        'total'          => 'decimal:2',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function venda()
    {
        return $this->belongsTo(Venda::class);
    }

    public function itens()
    {
        return $this->hasMany(OrcamentoItem::class);
    }
}
