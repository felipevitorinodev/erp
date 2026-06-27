<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmpresaRequest;
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

    public function create(){
        return $this->empresaService->create();
    } 

    public function store(EmpresaRequest $request){
        return $this->empresaService->store($request);
    }

    public function show(){
        return false;
    }

    public function edit(){
        return false;
    }

    public function update(){
        return false;
    }

    public function destroy(){
        return false;
    }
}
