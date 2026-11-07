<?php

namespace App\Http\Controllers\Fornecedores;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fornecedores\FornecedorRequest;
use App\Models\Fornecedores\Fornecedor;
use App\Services\Fornecedores\FornecedorService;
use Illuminate\Http\Request;

class FornecedorController extends Controller
{
    public function __construct(
        protected FornecedorService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(FornecedorRequest $request)
    {
        return $this->service->store($request);
    }

    public function show()
    {
        return false;
    }

    public function edit(Fornecedor $fornecedor)
    {
        return $this->service->edit($fornecedor);
    }

    public function update(FornecedorRequest $request, Fornecedor $fornecedor)
    {
        return $this->service->update($request, $fornecedor);
    }

    public function destroy(Fornecedor $fornecedor)
    {
        return $this->service->destroy($fornecedor->id);
    }
}
