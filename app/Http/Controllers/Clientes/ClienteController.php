<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\ClienteRequest;
use App\Models\Clientes\Cliente;
use App\Services\Clientes\ClienteService;
use Illuminate\Http\Request;


class ClienteController extends Controller
{

    public function __construct(
        protected ClienteService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create(){
        return $this->service->create();
    } 

    public function store(ClienteRequest $request){
        return $this->service->store($request);
    }

    public function show(){
        return false;
    }

    public function edit(Cliente $cliente)
    {
        return $this->service->edit($cliente);
    }

    public function update(ClienteRequest $request, Cliente $cliente)
    {
        return $this->service->update($request, $cliente);
    }

    public function destroy(Cliente $cliente){
        return $this->service->destroy($cliente->id);
    }
}
