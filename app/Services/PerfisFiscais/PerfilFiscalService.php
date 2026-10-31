<?php

namespace App\Services\PerfisFiscais;

use App\Models\PerfisFiscais\PerfilFiscal;
use App\Repositories\PerfisFiscais\PerfilFiscalRepository;
use Illuminate\Http\Request;

class PerfilFiscalService
{
    public function __construct(
        protected PerfilFiscalRepository $repository
    ) {}

    public function index(Request $request)
    {
        return view('perfil-fiscal.index', [
            'perfis' => $this->repository->index($request),
            'filtros' => $request->only(['busca']),
        ]);
    }

    public function create()
    {
        return view('perfil-fiscal.create');
    }

    public function store($request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('perfilFiscal.index')
            ->with('success', 'Perfil fiscal cadastrado. Produtos sem CFOP próprio passam a usá-lo na próxima emissão.');
    }

    public function edit(PerfilFiscal $perfil)
    {
        $this->autorizar($perfil);

        return view('perfil-fiscal.edit', ['perfil' => $perfil]);
    }

    public function update($request, PerfilFiscal $perfil)
    {
        $this->autorizar($perfil);
        $this->repository->update($perfil, $request->validated());

        return redirect()->route('perfilFiscal.index')
            ->with('success', 'Perfil fiscal atualizado. Notas já autorizadas não mudam.');
    }

    public function destroy(int $id)
    {
        $this->repository->destroy($id);

        return redirect()->route('perfilFiscal.index')
            ->with('success', 'Perfil fiscal excluído. Os produtos ligados a ele voltam ao padrão da empresa.');
    }

    private function autorizar(PerfilFiscal $perfil): void
    {
        if ((int) $perfil->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }
}
