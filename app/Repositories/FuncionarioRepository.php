<?php

namespace App\Repositories;

use App\Models\Funcionario;

class FuncionarioRepository
{
    public function index()
    {
        return Funcionario::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome')
            ->get();
    }

    public function store(array $data)
    {
        $data['empresa_id'] = auth()->user()->empresa_id;
        return Funcionario::create($data);
    }

    public function update(Funcionario $funcionario, array $data)
    {
        $funcionario->update($data);

        return $funcionario;
    }

    public function destroy($id)
    {
        $funcionario = Funcionario::where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);
        $funcionario->delete();

        return $funcionario;
    }
}