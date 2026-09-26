<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrcamentoRequest;
use App\Models\Orcamento;
use App\Services\OrcamentoService;
use Illuminate\Http\Request;

class OrcamentoController extends Controller
{
    public function __construct(
        protected OrcamentoService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(OrcamentoRequest $request)
    {
        return $this->service->store($request);
    }

    public function show(Orcamento $orcamento)
    {
        return $this->service->show($orcamento);
    }

    public function edit(Orcamento $orcamento)
    {
        return $this->service->edit($orcamento);
    }

    public function update(OrcamentoRequest $request, Orcamento $orcamento)
    {
        return $this->service->update($request, $orcamento);
    }

    public function aprovar(Orcamento $orcamento)
    {
        return $this->service->aprovar($orcamento);
    }

    public function recusar(Orcamento $orcamento)
    {
        return $this->service->recusar($orcamento);
    }

    public function cancelar(Orcamento $orcamento)
    {
        return $this->service->cancelar($orcamento);
    }

    public function destroy(Orcamento $orcamento)
    {
        return $this->service->destroy($orcamento);
    }

    public function pdf(Orcamento $orcamento)
    {
        return $this->service->pdf($orcamento);
    }
}
