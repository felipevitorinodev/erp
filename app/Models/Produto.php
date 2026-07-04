<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'produtos';

    protected $fillable = [
        'empresa_id',
        'grupo_id',
        'unidade_medida_id',
        'fornecedor_id',
        'codigo',
        'codigo_barras',
        'referencia',
        'nome',
        'descricao',
        'tipo',
        'preco_custo',
        'preco_venda',
        'preco_minimo',
        'margem_lucro',
        'estoque_minimo',
        'estoque_maximo',
        'estoque_atual',
        'peso',
        'altura',
        'largura',
        'profundidade',
        'foto_url',
        'ncm',
        'cest',
        'cfop_padrao',
        'origem',
        'cst_icms',
        'cst_pis',
        'cst_cofins',
        'aliquota_icms',
        'aliquota_pis',
        'aliquota_cofins',
        'controla_estoque',
        'ativo',
    ];

    protected $casts = [
        'ativo'                     => 'boolean',
        'controla_estoque'          => 'boolean',
        'permite_venda_sem_estoque' => 'boolean',
        'preco_custo'               => 'decimal:4',
        'preco_venda'               => 'decimal:4',
        'preco_minimo'              => 'decimal:4',
        'estoque_minimo'            => 'decimal:3',
        'estoque_maximo'            => 'decimal:3',
        'estoque_atual'             => 'decimal:3',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function grupos()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function unidadeMedida()
    {
        return $this->belongsTo(UnidadeMedida::class, 'unidade_medida_id');
    }

    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class);
    }
}