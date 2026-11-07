<?php

namespace App\Models\Usuarios;

use App\Models\Empresas\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Perfil extends Model
{
    protected $table = 'perfis';

    protected $fillable = [
        'empresa_id',
        'slug',
        'nome',
        'permissoes',
        'sistema',
        'ativo',
    ];

    protected $casts = [
        'permissoes' => 'array',
        'sistema' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
