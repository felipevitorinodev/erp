<?php

namespace App\Models\Nfes;

use App\Models\Empresas\Empresa;
use App\Models\Vendas\Venda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nfe extends Model
{
    use SoftDeletes;

    public const MODELO_NFE = 55;

    public const MODELO_NFCE = 65;

    protected $table = 'nfes';

    protected $fillable = [
        'empresa_id',
        'venda_id',
        'modelo',
        'numero',
        'serie',
        'chave_acesso',
        'situacao',
        'ambiente',
        'xml_assinado',
        'xml_autorizado',
        'xml_cancelamento',
        'protocolo',
        'recibo',
        'codigo_status',
        'motivo_status',
        'data_autorizacao',
        'data_cancelamento',
    ];

    protected $casts = [
        'modelo' => 'integer',
        'ambiente' => 'integer',
        'data_autorizacao' => 'datetime',
        'data_cancelamento' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function podeCancelar(): bool
    {
        if ($this->situacao !== 'autorizada' || $this->data_autorizacao === null) {
            return false;
        }

        return $this->data_autorizacao->gt(now()->subHours(24));
    }
}
