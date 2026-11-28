<?php

namespace App\Support;

use App\Models\Produtos\Produto;
use App\Models\Vendas\VendaItem;
use Illuminate\Support\Collection;

/**
 * Itens do formulário de venda que voltaram por erro de validação (old('itens')), no formato que o
 * venda/_form desenha: VendaItem sem gravar. Usado nas telas "Nova NFC-e" e "Nova NFS-e", em que a venda ainda não existe.
 */
class ItensReenviados
{
    /**
     * @return Collection<int, VendaItem>
     */
    public static function daSessao(int $empresaId): Collection
    {
        $enviados = old('itens');
        if (! is_array($enviados)) {
            return collect();
        }

        // Mantém os índices do formulário: o venda/_form lê a quantidade em old('itens.<índice>.quantidade').
        $enviados = array_filter($enviados, 'is_array');
        $ids = array_values(array_filter(array_map(fn (array $item) => (int) ($item['produto_id'] ?? 0), $enviados)));
        $produtos = Produto::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $ids)
            ->get(['id', 'nome', 'codigo'])
            ->keyBy('id');

        return collect($enviados)
            ->filter(fn (array $item) => $produtos->has((int) ($item['produto_id'] ?? 0)))
            ->map(function (array $item) use ($produtos) {
                $produto = $produtos[(int) $item['produto_id']];
                $quantidade = self::numero($item['quantidade'] ?? 1);
                $preco = self::numero($item['preco_unitario'] ?? 0);
                $desconto = self::numero($item['desconto'] ?? 0);

                return new VendaItem([
                    'produto_id' => $produto->id,
                    'produto_nome' => $produto->nome,
                    'produto_codigo' => $produto->codigo,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $preco,
                    'desconto' => $desconto,
                    'total' => max(0, round($quantidade * $preco - $desconto, 2)),
                ]);
            });
    }

    /** Aceita "1.234,56" (tela) e "1234.56" (já normalizado). */
    private static function numero(mixed $valor): float
    {
        $texto = trim((string) $valor);
        if (str_contains($texto, ',')) {
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        }

        return is_numeric($texto) ? (float) $texto : 0.0;
    }
}
