<?php

namespace App\Http\Controllers\Funcionarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funcionarios\FuncionarioRequest;
use App\Models\Funcionarios\Funcionario;
use App\Services\Funcionarios\FuncionarioService;
use Illuminate\Http\Request;

class FuncionarioController extends Controller
{
    public function __construct(
        protected FuncionarioService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
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