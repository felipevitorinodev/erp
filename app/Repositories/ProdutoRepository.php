<?php

namespace App\Repositories;

use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProdutoRepository
{
    public function index(?Request $request = null)
    {
        $query = Produto::with(['grupos', 'unidadeMedida'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome');

        if ($request) {
            if ($request->filled('busca')) {
                $busca = $request->busca;
                $query->where(function ($q) use ($busca) {
                    $q->where('nome', 'like', "%{$busca}%")
                        ->orWhere('codigo', 'like', "%{$busca}%")
                        ->orWhere('codigo_barras', 'like', "%{$busca}%");
                });
            }
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }
            if ($request->filled('grupo_id')) {
                $query->where('grupo_id', $request->grupo_id);
            }
        }

        return $query->paginate(20);
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $data['empresa_id'] = auth()->user()->empresa_id;

            $estoqueInicial = (float) ($data['estoque_atual'] ?? 0);
            if (empty($data['controla_estoque'])) {
                $estoqueInicial = 0;
                $data['estoque_atual'] = 0;
            }

            $produto = Produto::create($data);

            if (!empty($data['controla_estoque']) && $estoqueInicial > 0) {
                MovimentacaoEstoque::create([
                    'empresa_id'     => $produto->empresa_id,
                    'produto_id'     => $produto->id,
                    'user_id'        => auth()->id(),
                    'tipo'           => 'ajuste',
                    'quantidade'     => $estoqueInicial,
                    'estoque_antes'  => 0,
                    'estoque_depois' => $estoqueInicial,
                    'motivo'         => 'Estoque inicial',
                    'origem'         => 'ajuste_manual',
                    'origem_id'      => $produto->id,
                ]);
            }

            return $produto;
        });
    }

    public function update(Produto $produto, array $data)
    {
        // Estoque atual não deve ser alterado pelo form após cadastro
        unset($data['estoque_atual']);

        $produto->update($data);

        return $produto;
    }

    public function destroy($id)
    {
        $produto = Produto::where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);
        $produto->delete();

        return $produto;
    }
}
