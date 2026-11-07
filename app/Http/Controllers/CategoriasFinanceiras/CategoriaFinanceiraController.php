<?php

namespace App\Http\Controllers\CategoriasFinanceiras;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoriasFinanceiras\CategoriaFinanceiraRequest;
use App\Models\CategoriasFinanceiras\CategoriaFinanceira;
use App\Services\CategoriasFinanceiras\CategoriaFinanceiraService;
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
