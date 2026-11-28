<?php

namespace App\Http\Controllers\Nfses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nfses\NfseCancelarRequest;
use App\Http\Requests\Nfses\NfseEmitirRequest;
use App\Http\Requests\Nfses\NfseNovaRequest;
use App\Models\Empresas\Empresa;
use App\Models\Nfses\Nfse;
use App\Models\Vendas\Venda;
use App\Services\Nfses\NfseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NfseController extends Controller
{
    public function __construct(
        protected NfseService $nfseService
    ) {}

    public function index(Request $request)
    {
        return $this->nfseService->index($request);
    }

    public function create(Venda $venda)
    {
        return $this->nfseService->create($venda);
    }

    public function store(NfseEmitirRequest $request, Venda $venda)
    {
        return $this->nfseService->emitir($venda, $request->validated());
    }

    public function nova()
    {
        return $this->nfseService->nova();
    }

    public function salvarNova(NfseNovaRequest $request)
    {
        return $this->nfseService->salvarNova($request, $request->entradaNfse());
    }

    public function show(Nfse $nfse)
    {
        return $this->nfseService->show($nfse);
    }

    public function consultar(Nfse $nfse)
    {
        return $this->nfseService->consultar($nfse);
    }

    public function cancelar(NfseCancelarRequest $request, Nfse $nfse)
    {
        return $this->nfseService->cancelar($nfse, (string) $request->validated('codigo_motivo'), (string) $request->validated('motivo'));
    }

    public function xml(Request $request, Nfse $nfse)
    {
        return $this->nfseService->xml($nfse, (string) $request->query('tipo', 'nfse'));
    }

    public function danfse(Nfse $nfse)
    {
        return $this->nfseService->danfse($nfse);
    }

    public function verificar(Empresa $empresa): RedirectResponse
    {
        abort_unless((int) $empresa->id === (int) auth()->user()->empresa_id, 404);

        return redirect()
            ->to(route('empresa.edit', $empresa).'#nfse')
            ->with('nfse_verificacao', $this->nfseService->verificar($empresa));
    }
}
