<?php

namespace App\Models\Estoque;

use App\Models\ContasPagar\ContaPagar;
use App\Models\Empresas\Empresa;
use App\Models\Fornecedores\Fornecedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntradaEstoque extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'entradas_estoque';

    protected $fillable = [
        'empresa_id',
        'fornecedor_id',
        'numero',
        'data_entrada',
        'subtotal',
        'desconto',
        'total',
        'observacoes',
        'situacao',
        'chave_acesso',
        'xml_importado',
        'numero_nfe',
        'serie_nfe',
        'data_emissao_nfe',
    ];

    protected $casts = [
        'data_entrada'     => 'date',
        'data_emissao_nfe' => 'date',
        'subtotal'         => 'decimal:2',
        'desconto'         => 'decimal:2',
        'total'            => 'decimal:2',
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
        return $this->hasMany(EntradaEstoqueItem::class);
    }

    public function contasPagar()
    {
        return $this->hasMany(ContaPagar::class);
    }
}
