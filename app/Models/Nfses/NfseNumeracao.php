<?php

namespace App\Models\Nfses;

use App\Models\Empresas\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NfseNumeracao extends Model
{
    protected $table = 'nfse_numeracoes';

    protected $fillable = [
        'empresa_id',
        'ambiente',
        'serie',
        'ultimo_numero',
    ];

    protected $casts = [
        'ambiente' => 'integer',
        'ultimo_numero' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
