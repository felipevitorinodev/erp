<?php

namespace App\Http\Controllers;

use App\Http\Requests\FuncionarioRequest;
use App\Models\Funcionario;
use App\Services\FuncionarioService;

class FuncionarioController extends Controller
{
    public function __construct(
        protected FuncionarioService $service
    ) {}

    public function index()
    {
        return $this->service->index();
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(FuncionarioRequest $request)
    {
        return $this->service->store($request);
    }

    public function show()
    {
        return false;
    }

    public function edit(Funcionario $funcionario)
    {
        return $this->service->edit($funcionario);
    }

    public function update(FuncionarioRequest $request, Funcionario $funcionario)
    {
        return $this->service->update($request, $funcionario);
    }

    public function destroy(Funcionario $funcionario)
    {
        return $this->service->destroy($funcionario->id);
    }
}