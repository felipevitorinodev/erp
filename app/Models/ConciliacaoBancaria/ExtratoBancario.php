<?php

namespace App\Models\ConciliacaoBancaria;

use App\Models\Empresas\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtratoBancario extends Model
{
    protected $table = 'extratos_bancarios';

    protected $fillable = [
        'empresa_id',
        'arquivo_nome',
        'arquivo_hash',
        'conta_bancaria',
        'data_inicio',
        'data_fim',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function transacoes(): HasMany
    {
        return $this->hasMany(TransacaoBancaria::class, 'extrato_bancario_id');
    }
}
