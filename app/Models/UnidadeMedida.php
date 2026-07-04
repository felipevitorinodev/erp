<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnidadeMedida extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'unidades_medida';

    protected $fillable = [
        'empresa_id',
        'nome',
        'sigla',
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