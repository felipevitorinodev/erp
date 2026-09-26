<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoriaFinanceira extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'categorias_financeiras';

    protected $fillable = [
        'empresa_id',
        'nome',
        'tipo',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopeDaEmpresa(Builder $query, ?int $empresaId = null): Builder
    {
        $empresaId ??= (int) auth()->user()?->empresa_id;

        return $query->where('empresa_id', $empresaId);
    }
}
