<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornecedor extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'fornecedores';
 
    protected $fillable = [
        'empresa_id',
        'nome',
        'nome_fantasia',
        'cpf',
        'cnpj',
        'inscricao_estadual',
        'inscricao_municipal',
        'email',
        'telefone',
        'celular',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'estado',
        'observacoes',
        'ativo',
    ];
 
    protected $casts = [
        'ativo' => 'boolean',
    ];
 
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
