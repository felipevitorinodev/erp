<?php

namespace App\Services;

use App\Models\Funcionario;
use App\Repositories\FuncionarioRepository;

class FuncionarioService
{
    public function __construct(
        protected FuncionarioRepository $repository
    ) {}

    public function index()
    {
        $funcionarios = $this->repository->index();

        return view('funcionario.index', ['funcionarios' => $funcionarios]);
    }

    public function create()
    {
        return view('funcionario.create');
    }

    public function store($request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('funcionario.index')
            ->with('success', 'Funcionário cadastrado com sucesso.');
    }

    public function edit(Funcionario $funcionario)
    {
        return view('funcionario.edit', [
            'funcionario' => $funcionario
        ]);
    }

    public function update($request, Funcionario $funcionario)
    {
        $this->repository->update(
            $funcionario,
            $request->validated()
        );

        return redirect()
            ->route('funcionario.index')
            ->with('success', 'Funcionário editado com sucesso.');
    }

    public function destroy($id)
    {
        $this->repository->destroy($id);

        return redirect()->route('funcionario.index')
            ->with('success', 'Funcionário apagado com sucesso.');
    }
}