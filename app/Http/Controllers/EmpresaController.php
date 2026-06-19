<?php

namespace App\Http\Controllers;

use App\Services\EmpresaService;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{

    public function __construct(
        protected EmpresaService $empresaService
    ) {}

    public function index(){
        return $this->empresaService->index();
    }

    public function create(){
        return false;
    } 

    public function store(){
        return false;
    }

    public function show(){
        return false;
    }

    public function edit(){
        return false;
    }

    public function update(){
        return false;
    }

    public function destroy(){
        return false;
    }
}
