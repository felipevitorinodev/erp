<?php

namespace App\Http\Controllers;

use App\Services\EmpresaService;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{

    public function __construct(
        protected EmpresaService $empresaService
    ) {}

    public function index(){
        return $this->empresaService->index();
    }
}
