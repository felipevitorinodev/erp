<?php

namespace App\Http\Controllers;

use App\Models\Comissao;
use App\Services\ComissaoService;
use Illuminate\Http\Request;

class ComissaoController extends Controller
{
    public function __construct(
        protected ComissaoService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function pagar(Comissao $comissao)
    {
        return $this->service->pagar($comissao);
    }
}
