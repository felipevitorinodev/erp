<?php

namespace App\Services;

use App\Repositories\EmpresaRepository;

class EmpresaService
{
    public function __construct
    (
        protected EmpresaRepository $empresaRepository
    ){}

    public function index() {
        return $this->empresaRepository->index();
    }
}
