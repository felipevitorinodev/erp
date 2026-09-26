<?php

namespace App\Repositories;

use App\Models\Empresa;
use Illuminate\Http\Request;

class EmpresaRepository
{
    public function __construct(
        protected Empresa $empresa
    ) {}

    public function index(?Request $request = null)
    {
        $query = $this->empresa::query()->orderBy('razao_social');

        if ($request && $request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('razao_social', 'like', "%{$busca}%")
                    ->orWhere('nome_fantasia', 'like', "%{$busca}%")
                    ->orWhere('cnpj', 'like', "%{$busca}%");
            });
        }

        return $query->paginate(20);
    }

    public function store(array $request)
    {
        return $this->empresa->create($request);
    }

    public function edit(Empresa $empresa)
    {
        return $this->empresa->findOrFail($empresa->id);
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
