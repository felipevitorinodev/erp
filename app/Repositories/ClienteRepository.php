<?php

namespace App\Repositories;

use App\Models\Cliente;

class ClienteRepository
{
    /**
     * Create a new class instance.
     */
    public function __construct
    (
        protected Cliente $cliente
    )
    {}

    public function index(){
        return $this->cliente::where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome')
            ->get();
    }

    public function store(array $request)
    {
        $request['empresa_id'] = auth()->user()->empresa_id;
        return $this->cliente->create($request);
    }

    public function edit(Cliente $cliente)
    {
        $cliente = $this->cliente->where('empresa_id', auth()->user()->empresa_id)->findOrFail($cliente->id);

        return $cliente;
    }

    public function update(Cliente $cliente, array $dados)
    {
        $cliente->update($dados);

        return $cliente;
    }

    public function destroy($id)
    {
        $cliente = $this->cliente->where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);
        return $cliente->delete();
    }
}
