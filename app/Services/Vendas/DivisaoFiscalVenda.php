<?php

namespace App\Services\Vendas;

use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use Illuminate\Support\Collection;

/**
 * Venda mista: os produtos saem na NF-e e os serviços na NFS-e.
 * O desconto e o acréscimo da venda são rateados pela proporção do total de cada parte;
 * a parte dos produtos é arredondada e a dos serviços fica com a diferença, para a soma bater com a venda.
 */
class DivisaoFiscalVenda
{
    public const TIPO_SERVICO = 2;

    public static function ehServico(VendaItem $item): bool
    {
        return (int) ($item->produto?->tipo ?? 1) === self::TIPO_SERVICO;
    }

    public static function temServico(Venda $venda): bool
    {
        $venda->loadMissing('itens.produto');

        return $venda->itens->contains(fn (VendaItem $item) => self::ehServico($item));
    }

    public static function temProduto(Venda $venda): bool
    {
        $venda->loadMissing('itens.produto');

        return $venda->itens->contains(fn (VendaItem $item) => ! self::ehServico($item));
    }

    /** Venda com serviço e sem produto: não tem NF-e, só NFS-e. Venda sem itens não entra aqui. */
    public static function somenteServicos(Venda $venda): bool
    {
        return self::temServico($venda) && ! self::temProduto($venda);
    }

    /**
     * Cópia da venda só com os produtos (para a NF-e). Sem serviço na venda, devolve a própria venda sem mexer em nada.
     */
    public static function somenteProdutos(Venda $venda): Venda
    {
        if (! self::temServico($venda)) {
            return $venda;
        }

        $parte = self::parte($venda, false);
        $copia = clone $venda;
        $copia->setRelation('itens', $parte['itens']);
        $copia->desconto = $parte['desconto'];
        $copia->acrescimo = $parte['acrescimo'];

        return $copia;
    }

    /**
     * @return array{itens: Collection<int, VendaItem>, total_itens: float, desconto: float, acrescimo: float, liquido: float}
     */
    public static function parte(Venda $venda, bool $servicos): array
    {
        $venda->loadMissing('itens.produto');

        $todos = $venda->itens->values();
        $itens = $todos->filter(fn (VendaItem $item) => self::ehServico($item) === $servicos)->values();
        $produtos = $todos->reject(fn (VendaItem $item) => self::ehServico($item));

        $totalGeral = round((float) $todos->sum(fn (VendaItem $item) => (float) $item->total), 2);
        $totalProdutos = round((float) $produtos->sum(fn (VendaItem $item) => (float) $item->total), 2);
        $totalParte = round((float) $itens->sum(fn (VendaItem $item) => (float) $item->total), 2);

        $desconto = round((float) $venda->desconto, 2);
        $acrescimo = round((float) $venda->acrescimo, 2);

        $descontoProdutos = $totalGeral > 0 ? round($desconto * $totalProdutos / $totalGeral, 2) : 0.0;
        $acrescimoProdutos = $totalGeral > 0 ? round($acrescimo * $totalProdutos / $totalGeral, 2) : 0.0;

        $descontoParte = $servicos ? round($desconto - $descontoProdutos, 2) : $descontoProdutos;
        $acrescimoParte = $servicos ? round($acrescimo - $acrescimoProdutos, 2) : $acrescimoProdutos;

        return [
            'itens' => $itens,
            'total_itens' => $totalParte,
            'desconto' => $descontoParte,
            'acrescimo' => $acrescimoParte,
            'liquido' => round($totalParte - $descontoParte + $acrescimoParte, 2),
        ];
    }
}
