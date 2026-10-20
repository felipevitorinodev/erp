<?php

namespace App\Models\OrdensServico;

use App\Models\Produtos\Produto;
use Illuminate\Database\Eloquent\Model;

class OrdemServicoItem extends Model
{
    protected $table = 'ordem_servico_itens';

    protected $fillable = [
        'ordem_servico_id',
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

    public function ordemServico()
    {
        return $this->belongsTo(OrdemServico::class);
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }
}
