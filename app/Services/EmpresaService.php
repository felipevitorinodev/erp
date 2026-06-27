<?php

namespace App\Services;

use App\Http\Requests\EmpresaRequest;
use App\Models\Empresa;
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

    public function edit(Empresa $empresa)
    {
        return view('empresa.edit', [
            'empresa' => $empresa
        ]);
    }

    public function update(EmpresaRequest $request, Empresa $empresa)
    {
        $this->empresaRepository->update(
            $empresa,
            $request->validated()
        );

        return redirect()
            ->route('empresa.index')
            ->with('success', 'Empresa editada com sucesso.');
    }

    public function destroy($id)
    {
        $this->empresaRepository->destroy($id);

        return redirect()->route('empresa.index')
            ->with('success', 'Empresa apagada com sucesso.');
    }                       
}
