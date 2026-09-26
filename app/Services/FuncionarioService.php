<?php

namespace App\Services;

use App\Models\Funcionario;
use App\Repositories\FuncionarioRepository;
use Illuminate\Http\Request;

class FuncionarioService
{
    public function __construct(
        protected FuncionarioRepository $repository
    ) {}

    public function index(Request $request)
    {
        $funcionarios = $this->repository->index($request);

        return view('funcionario.index', [
            'funcionarios' => $funcionarios,
            'filtros'      => $request->only(['busca']),
        ]);
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
        if ((int) $funcionario->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        return view('funcionario.edit', [
            'funcionario' => $funcionario
        ]);
    }

    public function update($request, Funcionario $funcionario)
    {
        if ((int) $funcionario->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

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