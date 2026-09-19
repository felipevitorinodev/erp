<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContaPagarRequest;
use App\Models\ContaPagar;
use App\Services\ContaPagarService;
use Illuminate\Http\Request;

class ContaPagarController extends Controller
{
    public function __construct(
        protected ContaPagarService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(ContaPagarRequest $request)
    {
        return $this->service->store($request);
    }

    public function show(ContaPagar $contaPagar)
    {
        return $this->service->show($contaPagar);
    }

    public function edit(ContaPagar $contaPagar)
    {
        return $this->service->edit($contaPagar);
    }

    public function update(ContaPagarRequest $request, ContaPagar $contaPagar)
    {
        return $this->service->update($request, $contaPagar);
    }

    public function pagar(Request $request, ContaPagar $contaPagar)
    {
        return $this->service->pagar($request, $contaPagar);
    }

    public function cancelar(ContaPagar $contaPagar)
    {
        return $this->service->cancelar($contaPagar);
    }

    public function destroy(ContaPagar $contaPagar)
    {
        return $this->service->destroy($contaPagar);
    }
}
