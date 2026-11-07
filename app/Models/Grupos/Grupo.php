<?php

namespace App\Models\Grupos;

use App\Models\Empresas\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grupo extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'grupos';

    protected $fillable = [
        'empresa_id',
        'parent_id',
        'nome',
        'descricao',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    // grupo pai (se for subgrupo)
    public function parent()
    {
        return $this->belongsTo(Grupo::class, 'parent_id');
    }

    // subgrupos filhas
    public function subgrupos()
    {
        return $this->hasMany(Grupo::class, 'parent_id');
    }
}