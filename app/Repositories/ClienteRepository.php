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
        return $this->cliente::all();
    }

    public function store(array $request)
    {
        return $this->cliente->create($request);
    }

    public function edit(Cliente $cliente)
    {
        $cliente = $this->cliente->findOrFail($cliente->id);

        return $cliente;
    }

    public function update(Cliente $cliente, array $dados)
    {
        $cliente->update($dados);

        return $cliente;
    }

    public function destroy($id)
    {
        $cliente = $this->cliente->findOrFail($id);

        return $cliente->delete();
    }
}
