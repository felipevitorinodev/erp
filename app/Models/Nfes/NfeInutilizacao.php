<?php

namespace App\Models\Nfes;

use App\Models\Empresas\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NfeInutilizacao extends Model
{
    protected $table = 'nfe_inutilizacoes';

    protected $fillable = [
        'empresa_id',
        'modelo',
        'ambiente',
        'serie',
        'ano',
        'numero_inicial',
        'numero_final',
        'justificativa',
        'situacao',
        'protocolo',
        'codigo_status',
        'motivo_status',
        'xml',
    ];

    protected $casts = [
        'modelo' => 'integer',
        'ambiente' => 'integer',
        'numero_inicial' => 'integer',
        'numero_final' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
