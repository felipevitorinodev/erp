<?php

namespace App\Models\ConciliacaoBancaria;

use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use Illuminate\Database\Eloquent\Model;

class TransacaoBancaria extends Model
{
    protected $table = 'transacoes_bancarias';

    protected $fillable = [
        'empresa_id',
        'extrato_bancario_id',
        'fingerprint',
        'fitid',
        'data_transacao',
        'tipo',
        'valor',
        'descricao',
        'memo',
        'situacao',
        'conta_receber_id',
        'conta_pagar_id',
        'conciliado_em',
        'motivo_ignorado',
    ];

    protected $casts = [
        'data_transacao' => 'date',
        'valor' => 'decimal:2',
        'conciliado_em' => 'datetime',
    ];

    public function extrato()
    {
        return $this->belongsTo(ExtratoBancario::class, 'extrato_bancario_id');
    }

    public function contaReceber()
    {
        return $this->belongsTo(ContaReceber::class);
    }

    public function contaPagar()
    {
        return $this->belongsTo(ContaPagar::class);
    }
}
