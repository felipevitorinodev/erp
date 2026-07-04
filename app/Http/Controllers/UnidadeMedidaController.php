<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnidadeMedidaRequest;
use App\Models\UnidadeMedida;
use App\Services\UnidadeMedidaService;

class UnidadeMedidaController extends Controller
{
    public function __construct(
        protected UnidadeMedidaService $service
    ) {}

    public function index()
    {
        return $this->service->index();
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(UnidadeMedidaRequest $request)
    {
        return $this->service->store($request);
    }

    public function show()
    {
        return false;
    }

    public function edit(UnidadeMedida $unidadeMedida)
    {
        return $this->service->edit($unidadeMedida);
    }

    public function update(UnidadeMedidaRequest $request, UnidadeMedida $unidadeMedida)
    {
        return $this->service->update($request, $unidadeMedida);
    }

    public function destroy(UnidadeMedida $unidadeMedida)
    {
        return $this->service->destroy($unidadeMedida->id);
    }
}