<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimentacaoEstoque extends Model
{
    protected $table = 'movimentacoes_estoque';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'user_id',
        'tipo',
        'quantidade',
        'estoque_antes',
        'estoque_depois',
        'motivo',
        'origem',
        'origem_id',
    ];

    protected $casts = [
        'quantidade'     => 'decimal:2',
        'estoque_antes'  => 'decimal:2',
        'estoque_depois' => 'decimal:2',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
