<?php

namespace App\Http\Controllers\PerfisFiscais;

use App\Http\Controllers\Controller;
use App\Http\Requests\PerfisFiscais\PerfilFiscalRequest;
use App\Models\PerfisFiscais\PerfilFiscal;
use App\Services\PerfisFiscais\PerfilFiscalService;
use Illuminate\Http\Request;

class PerfilFiscalController extends Controller
{
    public function __construct(
        protected PerfilFiscalService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(PerfilFiscalRequest $request)
    {
        return $this->service->store($request);
    }

    public function edit(PerfilFiscal $perfilFiscal)
    {
        return $this->service->edit($perfilFiscal);
    }

    public function update(PerfilFiscalRequest $request, PerfilFiscal $perfilFiscal)
    {
        return $this->service->update($request, $perfilFiscal);
    }

    public function destroy(PerfilFiscal $perfilFiscal)
    {
        return $this->service->destroy($perfilFiscal->id);
    }
}
