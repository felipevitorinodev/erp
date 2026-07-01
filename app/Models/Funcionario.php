<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Funcionario extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'funcionarios';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'nome',
        'cpf',
        'rg',
        'data_nascimento',
        'email',
        'telefone',
        'celular',
        'cargo',
        'departamento',
        'salario',
        'data_admissao',
        'data_demissao',
        'tipo_contrato',
        'ativo',
        'observacoes',
    ];

    protected $casts = [
        'ativo'           => 'boolean',
        'salario'         => 'decimal:2',
        'data_nascimento' => 'date',
        'data_admissao'   => 'date',
        'data_demissao'   => 'date',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}