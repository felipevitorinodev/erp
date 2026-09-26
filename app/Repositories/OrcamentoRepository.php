<?php

namespace App\Repositories;

use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use Illuminate\Http\Request;

class OrcamentoRepository
{
    public function index(?Request $request = null)
    {
        $query = Orcamento::with(['cliente'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderByDesc('id');

        if ($request) {
            if ($request->filled('busca')) {
                $busca = $request->busca;
                $query->where(function ($q) use ($busca) {
                    $q->where('numero', 'like', "%{$busca}%")
                        ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$busca}%"));
                });
            }
            if ($request->filled('situacao')) {
                $query->where('situacao', $request->situacao);
            }
            if ($request->filled('data_inicio')) {
                $query->whereDate('data_orcamento', '>=', $request->data_inicio);
            }
            if ($request->filled('data_fim')) {
                $query->whereDate('data_orcamento', '<=', $request->data_fim);
            }
        }

        return $query->paginate(20);
    }

    public function findOrFail(int $id): Orcamento
    {
        return Orcamento::with(['cliente', 'itens.produto', 'usuario', 'venda'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);
    }

    public function proximoNumero(int $empresaId): string
    {
        $ultimo = Orcamento::where('empresa_id', $empresaId)->max('numero');
        $proximo = $ultimo ? ((int) $ultimo) + 1 : 1;

        return str_pad($proximo, 6, '0', STR_PAD_LEFT);
    }

    public function store(array $dados, array $itens): Orcamento
    {
        $orcamento = Orcamento::create($dados);
        $this->salvarItens($orcamento, $itens);

        return $orcamento;
    }

    public function update(Orcamento $orcamento, array $dados, array $itens): Orcamento
    {
        $orcamento->update($dados);
        $orcamento->itens()->delete();
        $this->salvarItens($orcamento, $itens);

        return $orcamento;
    }

    public function cancelar(Orcamento $orcamento): Orcamento
    {
        $orcamento->update(['situacao' => 'cancelado']);

        return $orcamento;
    }

    public function recusar(Orcamento $orcamento): Orcamento
    {
        $orcamento->update(['situacao' => 'recusado']);

        return $orcamento;
    }

    public function destroy(int $id): bool
    {
        $orcamento = Orcamento::where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);

        return (bool) $orcamento->delete();
    }

    private function salvarItens(Orcamento $orcamento, array $itens): void
    {
        foreach ($itens as $item) {
            OrcamentoItem::create(array_merge($item, ['orcamento_id' => $orcamento->id]));
        }
    }
}
