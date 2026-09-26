<?php

namespace App\Http\Controllers;

use App\Http\Requests\EntradaEstoqueRequest;
use App\Models\EntradaEstoque;
use App\Services\EntradaEstoqueService;

class EntradaEstoqueController extends Controller
{
    public function __construct(
        protected EntradaEstoqueService $service
    ) {}

    public function index(\Illuminate\Http\Request $request)
    {
        return $this->service->index($request);
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(EntradaEstoqueRequest $request)
    {
        return $this->service->store($request);
    }

    public function show(EntradaEstoque $entradaEstoque)
    {
        return $this->service->show($entradaEstoque);
    }

    public function edit(EntradaEstoque $entradaEstoque)
    {
        return $this->service->edit($entradaEstoque);
    }

    public function update(EntradaEstoqueRequest $request, EntradaEstoque $entradaEstoque)
    {
        return $this->service->update($request, $entradaEstoque);
    }

    public function confirmar(EntradaEstoque $entradaEstoque)
    {
        return $this->service->confirmar($entradaEstoque);
    }

    public function cancelar(EntradaEstoque $entradaEstoque)
    {
        return $this->service->cancelar($entradaEstoque);
    }

    public function destroy(EntradaEstoque $entradaEstoque)
    {
        return $this->service->destroy($entradaEstoque);
    }
}
