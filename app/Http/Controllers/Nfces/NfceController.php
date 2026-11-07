<?php

namespace App\Http\Controllers\Nfces;

use App\Http\Controllers\Controller;
use App\Models\Vendas\Venda;
use App\Services\Nfces\NfceService;

class NfceController extends Controller
{
    public function __construct(
        protected NfceService $nfceService
    ) {}

    public function danfe(Venda $venda)
    {
        return $this->nfceService->danfe($venda);
    }
}
