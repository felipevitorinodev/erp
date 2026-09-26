<?php

namespace App\Http\Controllers\Produtos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Produtos\ProdutoRequest;
use App\Models\Produtos\Produto;
use App\Services\Produtos\ProdutoService;
use Illuminate\Http\Request;

class ProdutoController extends Controller
{
    public function __construct(
        protected ProdutoService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
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