<?php

namespace App\Models\Fiscal;

use Illuminate\Database\Eloquent\Model;

/** Anexo VIII: item da lista × NBS × indicador de operação × cClassTrib. Serve de sugestão, não de regra. */
class NfseCorrelacao extends Model
{
    protected $table = 'nfse_correlacoes';

    protected $fillable = ['item', 'nbs', 'ind_op', 'class_trib'];
}
