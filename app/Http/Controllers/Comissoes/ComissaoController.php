<?php

namespace App\Http\Controllers\Comissoes;

use App\Http\Controllers\Controller;
use App\Models\Comissoes\Comissao;
use App\Services\Comissoes\ComissaoService;
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
