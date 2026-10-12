<?php

namespace App\Http\Controllers\Usuarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usuarios\PerfilRequest;
use App\Models\Usuarios\Perfil;
use App\Services\Usuarios\PerfilService;

class PerfilController extends Controller
{
    public function __construct(protected PerfilService $service) {}

    public function index()
    {
        return $this->service->index();
    }

    public function create()
    {
        return $this->service->create();
    }

    public function store(PerfilRequest $request)
    {
        return $this->service->store($request);
    }

    public function edit(Perfil $perfilAcesso)
    {
        return $this->service->edit($perfilAcesso);
    }

    public function update(PerfilRequest $request, Perfil $perfilAcesso)
    {
        return $this->service->update($request, $perfilAcesso);
    }

    public function destroy(Perfil $perfilAcesso)
    {
        return $this->service->destroy($perfilAcesso);
    }
}
