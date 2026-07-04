<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProdutoRequest;
use App\Models\Produto;
use App\Services\ProdutoService;

class ProdutoController extends Controller
{
    public function __construct(
        protected ProdutoService $service
    ) {}

    public function index()
    {
        return $this->service->index();
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(ProdutoRequest $request)
    {
        return $this->service->store($request);
    }

    public function show()
    {
        return false;
    }

    public function edit(Produto $produto)
    {
        return $this->service->edit($produto);
    }

    public function update(ProdutoRequest $request, Produto $produto)
    {
        return $this->service->update($request, $produto);
    }

    public function destroy(Produto $produto)
    {
        return $this->service->destroy($produto->id);
    }
}