<?php

namespace App\Http\Controllers\OrdensServico;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrdensServico\OrdemServicoRequest;
use App\Models\OrdensServico\OrdemServico;
use App\Services\OrdensServico\OrdemServicoService;
use Illuminate\Http\Request;

class OrdemServicoController extends Controller
{
    public function __construct(
        protected OrdemServicoService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(OrdemServicoRequest $request)
    {
        return $this->service->store($request);
    }

    public function show(OrdemServico $ordemServico)
    {
        return $this->service->show($ordemServico);
    }

    public function edit(OrdemServico $ordemServico)
    {
        return $this->service->edit($ordemServico);
    }

    public function update(OrdemServicoRequest $request, OrdemServico $ordemServico)
    {
        return $this->service->update($request, $ordemServico);
    }

    public function situacao(Request $request, OrdemServico $ordemServico)
    {
        return $this->service->alterarSituacao($request, $ordemServico);
    }

    public function faturar(OrdemServico $ordemServico)
    {
        return $this->service->faturar($ordemServico);
    }

    public function cancelar(OrdemServico $ordemServico)
    {
        return $this->service->cancelar($ordemServico);
    }

    public function destroy(OrdemServico $ordemServico)
    {
        return $this->service->destroy($ordemServico);
    }

    public function pdf(OrdemServico $ordemServico)
    {
        return $this->service->pdf($ordemServico);
    }
}
