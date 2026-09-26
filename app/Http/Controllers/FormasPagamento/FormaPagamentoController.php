<?php

namespace App\Http\Controllers\FormasPagamento;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormasPagamento\FormaPagamentoRequest;
use App\Models\FormasPagamento\FormaPagamento;
use App\Services\FormasPagamento\FormaPagamentoService;
use Illuminate\Http\Request;

class FormaPagamentoController extends Controller
{
    public function __construct(
        protected FormaPagamentoService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
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
