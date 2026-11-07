<?php

namespace App\Models\PerfisFiscais;

use App\Models\Empresas\Empresa;
use App\Models\Produtos\Produto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerfilFiscal extends Model
{
    use SoftDeletes;

    protected $table = 'perfis_fiscais';

    protected $fillable = [
        'empresa_id',
        'nome',
        'operacao',
        'uf_destino',
        'tipo_destinatario',
        'ncm',
        'cfop',
        'csosn',
        'cst_pis',
        'cst_cofins',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'perfil_fiscal_id');
    }
}
