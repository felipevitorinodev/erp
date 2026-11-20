<?php

namespace App\Http\Controllers\ConciliacaoBancaria;

use App\Http\Controllers\Controller;
use App\Models\ConciliacaoBancaria\ExtratoBancario;
use App\Models\ConciliacaoBancaria\TransacaoBancaria;
use App\Services\ConciliacaoBancaria\ConciliacaoBancariaService;
use Illuminate\Http\Request;

class ConciliacaoBancariaController extends Controller
{
    public function __construct(protected ConciliacaoBancariaService $service) {}

    public function index()
    {
        return $this->service->index();
    }

    public function importar(Request $request)
    {
        return $this->service->importar($request);
    }

    public function show(Request $request, ExtratoBancario $extratoBancario)
    {
        return $this->service->show($request, $extratoBancario);
    }

    public function buscarContas(Request $request)
    {
        return $this->service->buscarContas($request);
    }

    public function conciliar(Request $request, TransacaoBancaria $transacaoBancaria)
    {
        return $this->service->conciliar($request, $transacaoBancaria);
    }

    public function ignorar(Request $request, TransacaoBancaria $transacaoBancaria)
    {
        return $this->service->ignorar($request, $transacaoBancaria);
    }
}
