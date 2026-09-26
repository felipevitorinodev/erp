<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoriaFinanceiraRequest;
use App\Models\CategoriaFinanceira;
use App\Services\CategoriaFinanceiraService;
use Illuminate\Http\Request;

class CategoriaFinanceiraController extends Controller
{
    public function __construct(
        protected CategoriaFinanceiraService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(CategoriaFinanceiraRequest $request)
    {
        return $this->service->store($request);
    }

    public function edit(CategoriaFinanceira $categoriaFinanceira)
    {
        return $this->service->edit($categoriaFinanceira);
    }

    public function update(CategoriaFinanceiraRequest $request, CategoriaFinanceira $categoriaFinanceira)
    {
        return $this->service->update($request, $categoriaFinanceira);
    }

    public function destroy(CategoriaFinanceira $categoriaFinanceira)
    {
        return $this->service->destroy($categoriaFinanceira->id);
    }
}
