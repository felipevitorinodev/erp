<?php

namespace App\Repositories;

use App\Models\Empresa;

class EmpresaRepository
{
    /**
     * Create a new class instance.
     */
    public function __construct
    (
        protected Empresa $empresa
    )
    {}

    public function index(){
        return $this->empresa::all();
    }
}
