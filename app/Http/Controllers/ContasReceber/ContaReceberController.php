<?php

namespace App\Http\Controllers\ContasReceber;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContasReceber\ContaReceberRequest;
use App\Models\ContasReceber\ContaReceber;
use App\Services\ContasReceber\ContaReceberService;
use Illuminate\Http\Request;

class ContaReceberController extends Controller
{
    public function __construct(
        protected ContaReceberService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(ContaReceberRequest $request)
    {
        return $this->service->store($request);
    }

    public function show(ContaReceber $contaReceber)
    {
        return $this->service->show($contaReceber);
    }

    public function edit(ContaReceber $contaReceber)
    {
        return $this->service->edit($contaReceber);
    }

    public function update(ContaReceberRequest $request, ContaReceber $contaReceber)
    {
        return $this->service->update($request, $contaReceber);
    }

    public function receber(Request $request, ContaReceber $contaReceber)
    {
        return $this->service->receber($request, $contaReceber);
    }

    public function cancelar(ContaReceber $contaReceber)
    {
        return $this->service->cancelar($contaReceber);
    }

    public function destroy(ContaReceber $contaReceber)
    {
        return $this->service->destroy($contaReceber);
    }
}
