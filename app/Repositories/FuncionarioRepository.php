<?php

namespace App\Repositories;

use App\Models\Funcionario;

class FuncionarioRepository
{
    public function index()
    {
        return Funcionario::orderBy('nome')->get();
    }

    public function store(array $data)
    {
        return Funcionario::create($data);
    }

    public function update(Funcionario $funcionario, array $data)
    {
        $funcionario->update($data);

        return $funcionario;
    }

    public function destroy($id)
    {
        $funcionario = Funcionario::findOrFail($id);
        $funcionario->delete();

        return $funcionario;
    }
}