<?php

namespace App\Http\Controllers\Vendas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendas\VendaRequest;
use App\Models\Vendas\Venda;
use App\Services\Vendas\VendaService;
use Illuminate\Http\Request;

class VendaController extends Controller
{
    public function __construct(
        protected VendaService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(VendaRequest $request)
    {
        return $this->service->store($request);
    }

    public function show(Venda $venda)
    {
        return $this->service->show($venda);
    }

    public function edit(Venda $venda)
    {
        return $this->service->edit($venda);
    }

    public function update(VendaRequest $request, Venda $venda)
    {
        return $this->service->update($request, $venda);
    }

    public function confirmar(Venda $venda)
    {
        return $this->service->confirmar($venda);
    }

    public function cancelar(Venda $venda)
    {
        return $this->service->cancelar($venda);
    }

    public function destroy(Venda $venda)
    {
        return $this->service->destroy($venda);
    }

    public function pdf(Venda $venda)
    {
        return $this->service->pdf($venda);
    }
}
