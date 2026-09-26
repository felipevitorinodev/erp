<?php

namespace App\Models\ContasReceber;

use App\Models\CategoriasFinanceiras\CategoriaFinanceira;
use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\Vendas\Venda;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContaReceber extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'contas_receber';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'venda_id',
        'descricao',
        'valor',
        'valor_pago',
        'data_vencimento',
        'data_pagamento',
        'forma_pagamento',
        'categoria_id',
        'situacao',
        'observacoes',
    ];

    protected $casts = [
        'valor'           => 'decimal:2',
        'valor_pago'      => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento'  => 'date',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function venda()
    {
        return $this->belongsTo(Venda::class);
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaFinanceira::class, 'categoria_id');
    }
}
