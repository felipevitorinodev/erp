<?php

namespace App\Models\Nfes;

use App\Models\Empresas\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NfeNumeracao extends Model
{
    protected $table = 'nfe_numeracoes';

    protected $fillable = [
        'empresa_id',
        'modelo',
        'ambiente',
        'serie',
        'ultimo_numero',
    ];

    protected $casts = [
        'modelo' => 'integer',
        'ambiente' => 'integer',
        'ultimo_numero' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
