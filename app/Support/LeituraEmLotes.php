<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;

/**
 * Percorre uma consulta grande (exportação CSV) sem OFFSET.
 *
 * O lazy() do Laravel lê em lotes com LIMIT/OFFSET: o lote N relê e descarta tudo que veio antes, então
 * exportar um ano de vendas custa O(n²). Aqui a ordem é resolvida uma vez só (lista de ids, leitura enxuta)
 * e cada lote é buscado pela chave primária, com os mesmos filtros e eager loads da consulta original.
 */
final class LeituraEmLotes
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $consulta  consulta já filtrada e ordenada
     * @return LazyCollection<int, TModel>
     */
    public static function porChave(Builder $consulta, int $lote = 1000): LazyCollection
    {
        return LazyCollection::make(function () use ($consulta, $lote) {
            $modelo = $consulta->getModel();
            $chave = $modelo->getQualifiedKeyName();

            $ids = (clone $consulta)->setEagerLoads([])->toBase()->pluck($chave)->all();

            foreach (array_chunk($ids, $lote) as $parte) {
                $posicao = array_flip($parte);
                // Lote buscado pela chave primária: sem dica de índice de período (USE INDEX tiraria o acesso pela PK).
                $registros = IndiceBanco::remover((clone $consulta)->reorder())->whereIn($chave, $parte)->get()
                    ->sortBy(fn (Model $registro) => $posicao[$registro->getKey()] ?? PHP_INT_MAX);

                foreach ($registros as $registro) {
                    yield $registro;
                }
            }
        });
    }
}
