<?php

namespace App\Services;

use App\Http\Requests\ClienteRequest;

use App\Models\Cliente;
use App\Repositories\ClienteRepository;

class ClienteService
{
    public function __construct
    (
        protected ClienteRepository $repository
    ){}

    public function index() {
        $clientes = $this->repository->index();

        return view('cliente.index' , ['clientes' => $clientes]);
    }

    public function create() {
        return view('cliente.create');
    }

    public function store($request)
    {
    $this->repository->store($request->validated());

    return redirect()->route('cliente.index')
        ->with('success', 'Cliente cadastrado com sucesso.');
    }   

    public function edit(Cliente $cliente)
    {
        return view('cliente.edit', [
            'cliente' => $cliente
        ]);
    }

    public function update(ClienteRequest $request, Cliente $cliente)
    {
        $this->repository->update(
            $cliente,
            $request->validated()
        );

        return redirect()
            ->route('cliente.index')
            ->with('success', 'Cliente editado com sucesso.');
    }

    public function destroy($id)
    {
        $this->repository->destroy($id);

        return redirect()->route('cliente.index')
            ->with('success', 'Cliente apagado com sucesso.');
    }                       
}
