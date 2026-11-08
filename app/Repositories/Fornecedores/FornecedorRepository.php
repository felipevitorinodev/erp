<?php

namespace App\Repositories\Fornecedores;

use App\Models\Fornecedores\Fornecedor;
use Illuminate\Http\Request;

class FornecedorRepository
{
    public function index(?Request $request = null)
    {
        $query = Fornecedor::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome');

        if ($request && $request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('cnpj', 'like', "%{$busca}%")
                    ->orWhere('cpf', 'like', "%{$busca}%");
            });
        }

        return $query->paginate(20);
    }

    public function store(array $data)
    {
        $data['empresa_id'] = auth()->user()->empresa_id;

        return Fornecedor::create($data);
    }

    /** Cadastro rápido (produto e comandos) com os dados já validados por FornecedorRapidoRequest. */
    public function cadastrarRapido(array $dados): Fornecedor
    {
        return Fornecedor::create([
            'empresa_id' => auth()->user()->empresa_id,
            'nome' => trim($dados['nome']),
            'cnpj' => $dados['cnpj'] ?? null,
            'telefone' => $dados['telefone'] ?? null,
            'ativo' => true,
        ]);
    }

    public function update(Fornecedor $fornecedor, array $data)
    {
        unset($data['empresa_id']);

        $fornecedor->update($data);

        return $fornecedor;
    }

    public function destroy($id)
    {
        $fornecedor = Fornecedor::where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);
        $fornecedor->delete();

        return $fornecedor;
    }
}
