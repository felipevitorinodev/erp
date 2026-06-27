<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Services\ClienteService;


class ClienteController extends Controller
{

    public function __construct(
        protected ClienteService $service
    ) {}

    public function index(){
        return $this->service->index();
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
