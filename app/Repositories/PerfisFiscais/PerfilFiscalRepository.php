<?php

namespace App\Repositories\PerfisFiscais;

use App\Models\PerfisFiscais\PerfilFiscal;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PerfilFiscalRepository
{
    public function index(?Request $request = null)
    {
        $query = PerfilFiscal::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->withCount('produtos')
            ->orderBy('nome');

        if ($request && $request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('ncm', 'like', "%{$busca}%")
                    ->orWhere('cfop', 'like', "%{$busca}%");
            });
        }

        return $query->paginate(20);
    }

    public function store(array $data): PerfilFiscal
    {
        $data['empresa_id'] = auth()->user()->empresa_id;

        return PerfilFiscal::create($data);
    }

    public function update(PerfilFiscal $perfil, array $data): PerfilFiscal
    {
        unset($data['empresa_id']);
        $perfil->update($data);

        return $perfil;
    }

    public function ativosDaEmpresa(int $empresaId): Collection
    {
        return PerfilFiscal::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('id')
            ->get();
    }

    public function destroy(int $id): void
    {
        $perfil = PerfilFiscal::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);

        $perfil->delete();
    }
}
