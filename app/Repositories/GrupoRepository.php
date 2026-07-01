<?php

namespace App\Repositories;

use App\Models\Grupo;

class GrupoRepository
{
    /**
     * Create a new class instance.
     */
    public function __construct
    (
        protected Grupo $grupo
    )
    {}

    public function index(){
        return $this->grupo::all();
    }

    public function store(array $request)
    {
        return $this->grupo->create($request);
    }

    public function edit(Grupo $grupo)
    {
        $grupo = $this->grupo->findOrFail($grupo->id);

        return $grupo;
    }

    public function update(Grupo $grupo, array $dados)
    {
        $grupo->update($dados);

        return $grupo;
    }

    public function destroy($id)
    {
        $grupo = $this->grupo->findOrFail($id);

        return $grupo->delete();
    }
}
