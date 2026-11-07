<?php

namespace App\Http\Controllers\Empresas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Empresas\EmpresaRequest;
use App\Models\Empresas\Empresa;
use App\Services\Empresas\EmpresaService;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{

    public function __construct(
        protected EmpresaService $empresaService
    ) {}

    public function index(Request $request)
    {
        return $this->empresaService->index($request);
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

    public function edit(Empresa $empresa)
    {
        return $this->empresaService->edit($empresa);
    }

    public function update(EmpresaRequest $request, Empresa $empresa)
    {
        return $this->empresaService->update($request, $empresa);
    }

    public function destroy(Empresa $empresa){
        return $this->empresaService->destroy($empresa->id);
    }
}
