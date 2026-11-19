<?php

namespace App\Models\ContasReceber;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use Illuminate\Database\Eloquent\Model;

class CobrancaHistorico extends Model
{
    protected $table = 'cobrancas_historico';

    protected $fillable = [
        'empresa_id',
        'conta_receber_id',
        'user_id',
        'canal',
        'resultado',
        'contatado_em',
        'proxima_cobranca_em',
    ];

    protected $casts = [
        'contatado_em' => 'date',
        'proxima_cobranca_em' => 'date',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function contaReceber()
    {
        return $this->belongsTo(ContaReceber::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
