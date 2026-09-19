<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormaPagamentoRequest;
use App\Models\FormaPagamento;
use App\Services\FormaPagamentoService;

class FormaPagamentoController extends Controller
{
    public function __construct(
        protected FormaPagamentoService $service
    ) {}

    public function index()
    {
        return $this->service->index();
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(FormaPagamentoRequest $request)
    {
        return $this->service->store($request);
    }

    public function edit(FormaPagamento $formaPagamento)
    {
        return $this->service->edit($formaPagamento);
    }

    public function update(FormaPagamentoRequest $request, FormaPagamento $formaPagamento)
    {
        return $this->service->update($request, $formaPagamento);
    }

    public function destroy(FormaPagamento $formaPagamento)
    {
        return $this->service->destroy($formaPagamento->id);
    }
}
