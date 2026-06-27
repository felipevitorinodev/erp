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

    public function store(array $request)
    {
        return $this->empresa->create($request);
    }

    public function edit(Empresa $empresa)
    {
        $empresa = $this->empresa->findOrFail($empresa->id);

        return $empresa;
    }

    public function update(Empresa $empresa, array $dados)
    {
        $empresa->update($dados);

        return $empresa;
    }

    public function destroy($id)
    {
        $empresa = $this->empresa->findOrFail($id);

        return $empresa->delete();
    }
}
