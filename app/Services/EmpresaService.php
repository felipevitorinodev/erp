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
        $empresas = $this->empresaRepository->index();

        return view('empresa.index' , ['empresas' => $empresas]);
    }

    public function create() {
        return view('empresa.create');
    }

    public function store($request)
    {
    $this->empresaRepository->store($request->validated());

    return redirect()->route('empresa.index')
        ->with('success', 'Empresa cadastrada com sucesso.');
    }   
}
