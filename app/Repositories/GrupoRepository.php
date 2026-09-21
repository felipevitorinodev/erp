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
        return $this->grupo::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome')
            ->get();
    }

    public function principais()
    {
        return Grupo::where('empresa_id', auth()->user()->empresa_id)
            ->whereNull('parent_id')
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();
    }

    public function store(array $request)
    {
        $request['empresa_id'] = auth()->user()->empresa_id;
        return $this->grupo->create($request);
    }

    public function edit(Grupo $grupo)
    {
        $grupo = $this->grupo->where('empresa_id', auth()->user()->empresa_id)->findOrFail($grupo->id);

        return $grupo;
    }

    public function update(Grupo $grupo, array $dados)
    {
        $grupo->update($dados);

        return $grupo;
    }

    public function destroy($id)
    {
        $grupo = $this->grupo->where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);

        return $grupo->delete();
    }
}
