<?php

namespace App\Http\Controllers\Estoque;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estoque\EntradaEstoqueRequest;
use App\Http\Requests\Estoque\ImportarXmlRequest;
use App\Models\Estoque\EntradaEstoque;
use App\Services\Estoque\EntradaEstoqueService;
use Illuminate\Http\Request;

class EntradaEstoqueController extends Controller
{
    public function __construct(
        protected EntradaEstoqueService $service
    ) {}

    public function index(Request $request)
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

    public function importarXml(ImportarXmlRequest $request)
    {
        return $this->service->importarXml($request);
    }

    public function limparXml()
    {
        return $this->service->limparXml();
    }
}
