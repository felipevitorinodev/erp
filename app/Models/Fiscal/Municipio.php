<?php

namespace App\Models\Fiscal;

use Illuminate\Database\Eloquent\Model;

/** Municípios do IBGE (Anexo A da NFS-e Nacional). */
class Municipio extends Model
{
    protected $table = 'municipios';

    protected $fillable = ['codigo', 'nome', 'uf'];
}
