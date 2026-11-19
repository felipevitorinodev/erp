<?php

namespace App\Http\Controllers\Estoque;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estoque\PedidoCompraRequest;
use App\Models\Estoque\PedidoCompra;
use App\Services\Estoque\PedidoCompraService;
use Illuminate\Http\Request;

class PedidoCompraController extends Controller
{
    public function __construct(
        protected PedidoCompraService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(PedidoCompraRequest $request)
    {
        return $this->service->store($request);
    }

    public function show(PedidoCompra $pedidoCompra)
    {
        return $this->service->show($pedidoCompra);
    }

    public function edit(PedidoCompra $pedidoCompra)
    {
        return $this->service->edit($pedidoCompra);
    }

    public function update(PedidoCompraRequest $request, PedidoCompra $pedidoCompra)
    {
        return $this->service->update($request, $pedidoCompra);
    }

    public function aprovar(PedidoCompra $pedidoCompra)
    {
        return $this->service->aprovar($pedidoCompra);
    }

    public function cancelar(PedidoCompra $pedidoCompra)
    {
        return $this->service->cancelar($pedidoCompra);
    }

    public function destroy(PedidoCompra $pedidoCompra)
    {
        return $this->service->destroy($pedidoCompra);
    }
}
