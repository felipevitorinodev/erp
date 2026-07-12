<?php

namespace App\Repositories;

use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Produto;

class VendaRepository
{
    public function index()
    {
        return Venda::with(['cliente'])
            ->orderByDesc('id')
            ->get();
    }

    public function findOrFail(int $id): Venda
    {
        return Venda::with(['cliente', 'itens.produto', 'usuario'])
            ->findOrFail($id);
    }

    public function proximoNumero(int $empresaId): string
    {
        $ultimo = Venda::where('empresa_id', $empresaId)
            ->max('numero');

        $proximo = $ultimo ? ((int) $ultimo) + 1 : 1;

        return str_pad($proximo, 6, '0', STR_PAD_LEFT);
    }

    public function store(array $dados, array $itens): Venda
    {
        $venda = Venda::create($dados);

        $this->salvarItens($venda, $itens);

        return $venda;
    }

    public function update(Venda $venda, array $dados, array $itens): Venda
    {
        // Estorna estoque se estava confirmada antes
        if ($venda->situacao === 'confirmada') {
            $this->estornarEstoque($venda);
        }

        $venda->update($dados);

        $venda->itens()->delete();
        $this->salvarItens($venda, $itens);

        // Aplica estoque se a nova situação é confirmada
        if ($venda->situacao === 'confirmada') {
            $this->aplicarEstoque($venda->fresh('itens'));
        }

        return $venda;
    }

    public function confirmar(Venda $venda): Venda
    {
        $venda->update(['situacao' => 'confirmada']);
        $this->aplicarEstoque($venda->load('itens'));

        return $venda;
    }

    public function cancelar(Venda $venda): Venda
    {
        if ($venda->situacao === 'confirmada') {
            $this->estornarEstoque($venda);
        }

        $venda->update(['situacao' => 'cancelada']);

        return $venda;
    }

    public function destroy(int $id): bool
    {
        $venda = Venda::findOrFail($id);

        if ($venda->situacao === 'confirmada') {
            $this->estornarEstoque($venda);
        }

        return (bool) $venda->delete();
    }

    // -------------------------------------------------------------------------

    private function salvarItens(Venda $venda, array $itens): void
    {
        foreach ($itens as $item) {
            VendaItem::create(array_merge($item, ['venda_id' => $venda->id]));
        }
    }

    private function aplicarEstoque(Venda $venda): void
    {
        foreach ($venda->itens as $item) {
            if ($item->produto_id) {
                Produto::where('id', $item->produto_id)
                    ->where('controla_estoque', true)
                    ->decrement('estoque_atual', $item->quantidade);
            }
        }
    }

    private function estornarEstoque(Venda $venda): void
    {
        foreach ($venda->itens as $item) {
            if ($item->produto_id) {
                Produto::where('id', $item->produto_id)
                    ->where('controla_estoque', true)
                    ->increment('estoque_atual', $item->quantidade);
            }
        }
    }
}
