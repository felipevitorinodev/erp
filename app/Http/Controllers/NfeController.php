<?php

namespace App\Http\Controllers;

use App\Http\Requests\Nfes\NfeCreateRequest;
use App\Models\Nfe;
use App\Models\Vendas\Venda;
use App\Services\NfeService;
use Illuminate\Http\Request;

class NfeController extends Controller
{
    public function __construct(
        protected NfeService $nfeService
    ) {}

    public function index(Request $request)
    {
        return $this->nfeService->index($request);
    }

    public function create()
    {
        return $this->nfeService->create();
    }

    public function store(NfeCreateRequest $request)
    {
        return $this->nfeService->store($request);
    }

    public function emitir(Venda $venda)
    {
        return $this->nfeService->emitir($venda);
    }

    public function show(Nfe $nfe)
    {
        return $this->nfeService->show($nfe);
    }

    public function cancelar(Request $request, Nfe $nfe)
    {
        $dados = $request->validate([
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ], [
            'justificativa.required' => 'Informe a justificativa do cancelamento.',
            'justificativa.min' => 'A justificativa deve ter no mínimo 15 caracteres.',
            'justificativa.max' => 'A justificativa deve ter no máximo 255 caracteres.',
        ]);

        return $this->nfeService->cancelar($nfe, $dados['justificativa']);
    }

    public function danfe(Nfe $nfe)
    {
        return $this->nfeService->danfe($nfe);
    }

    public function consultar(Nfe $nfe)
    {
        return $this->nfeService->consultar($nfe);
    }

    public function xml(Request $request, Nfe $nfe)
    {
        return $this->nfeService->xml($nfe, $request->query('tipo') === 'cancelamento');
    }
}
