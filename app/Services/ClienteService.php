<?php

namespace App\Services;

use App\Http\Requests\ClienteRequest;

use App\Models\Cliente;
use App\Repositories\ClienteRepository;
use Illuminate\Http\Request;

class ClienteService
{
    public function __construct
    (
        protected ClienteRepository $repository
    ){}

    public function index(Request $request)
    {
        $clientes = $this->repository->index($request);

        return view('cliente.index', [
            'clientes' => $clientes,
            'filtros'  => $request->only(['busca']),
        ]);
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
        if ((int) $cliente->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        return view('cliente.edit', [
            'cliente' => $cliente
        ]);
    }

    public function update(ClienteRequest $request, Cliente $cliente)
    {
        if ((int) $cliente->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

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
