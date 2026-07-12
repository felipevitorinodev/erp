<?php

namespace App\Http\Controllers;

use App\Models\Venda;
use App\Services\VendaService;
use Illuminate\Http\Request;

class VendaController extends Controller
{
    public function __construct(
        protected VendaService $service
    ) {}

    public function index()
    {
        return $this->service->index();
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(Request $request)
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

    public function update(Request $request, Venda $venda)
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
}
