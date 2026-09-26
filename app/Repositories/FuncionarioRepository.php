<?php

namespace App\Repositories;

use App\Models\Funcionario;
use Illuminate\Http\Request;

class FuncionarioRepository
{
    public function index(?Request $request = null)
    {
        $query = Funcionario::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome');

        if ($request && $request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('cpf', 'like', "%{$busca}%");
            });
        }

        return $query->paginate(20);
    }

    public function store(array $data)
    {
        $data['empresa_id'] = auth()->user()->empresa_id;
        return Funcionario::create($data);
    }

    public function update(Funcionario $funcionario, array $data)
    {
        unset($data['empresa_id']);
        $data['empresa_id'] = $funcionario->empresa_id;

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
