<?php

namespace App\Repositories\Clientes;

use App\Models\Clientes\Cliente;
use Illuminate\Http\Request;

class ClienteRepository
{
    public function __construct(
        protected Cliente $cliente
    ) {}

    public function index(?Request $request = null)
    {
        $query = $this->cliente::where('empresa_id', auth()->user()->empresa_id)
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

    public function store(array $request)
    {
        $request['empresa_id'] = auth()->user()->empresa_id;

        return $this->cliente->create($request);
    }

    /** Cadastro rápido (balcão, NF-e e comandos) com os dados já validados por ClienteRapidoRequest. */
    public function cadastrarRapido(array $dados): Cliente
    {
        return $this->cliente->create([
            'empresa_id' => auth()->user()->empresa_id,
            'nome' => trim($dados['nome']),
            'cpf' => $dados['cpf'] ?? null,
            'cnpj' => $dados['cnpj'] ?? null,
            'telefone' => $dados['telefone'] ?? null,
            'email' => $dados['email'] ?? null,
            'ativo' => true,
        ]);
    }

    public function edit(Cliente $cliente)
    {
        return $this->cliente->where('empresa_id', auth()->user()->empresa_id)->findOrFail($cliente->id);
    }

    public function update(Cliente $cliente, array $dados)
    {
        unset($dados['empresa_id']);

        $cliente->update($dados);

        return $cliente;
    }

    public function destroy($id)
    {
        $cliente = $this->cliente->where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);

        return $cliente->delete();
    }
}
