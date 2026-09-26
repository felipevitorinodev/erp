<?php

namespace App\Http\Controllers;

use App\Http\Requests\GrupoRequest;
use App\Models\Grupo;
use App\Services\GrupoService;
use Illuminate\Http\Request;


class GrupoController extends Controller
{

    public function __construct(
        protected GrupoService $service
    ) {}

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create(){
        return $this->service->create();
    } 

    public function store(GrupoRequest $request){
        return $this->service->store($request);
    }

    public function show(){
        return false;
    }

    public function edit(Grupo $grupo)
    {
        return $this->service->edit($grupo);
    }

    public function update(GrupoRequest $request, Grupo $grupo)
    {
        return $this->service->update($request, $grupo);
    }

    public function destroy(Grupo $grupo){
        return $this->service->destroy($grupo->id);
    }
}
