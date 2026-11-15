<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Total da paginação guardado no cache entre telas.
 *
 * Toda troca de página refazia o COUNT(*) de todos os registros filtrados (centenas de milhares de vendas,
 * contas, movimentações). O total só muda quando alguém grava nas tabelas lidas pela consulta; até lá ele
 * vem do cache. A chave é o próprio SQL com os valores (empresa, filtros, permissões), então cada combinação
 * de filtros tem a sua contagem.
 */
final class ContagemPaginada
{
    /**
     * @param  list<string>  $dominios  domínios do CacheDados lidos pela consulta (ex.: vendas, cadastros)
     * @param  string|null  $indice  índice que cobre todos os filtros da listagem, indicado só no COUNT: com
     *                               vários índices começando por empresa_id o MySQL às vezes contava pelo que
     *                               não tem deleted_at e lia cada linha (1,7 s contra 0,4 s em 1 milhão de vendas)
     * @return Closure(): int para o 5º argumento do paginate()
     */
    public static function total(Builder $consulta, array $dominios, ?string $indice = null, int $segundos = 300): Closure
    {
        return function () use ($consulta, $dominios, $indice, $segundos): int {
            $base = clone $consulta->toBase();
            if ($indice !== null) {
                IndiceBanco::usar($base, $consulta->getModel()->getTable(), $indice);
            }

            return (int) CacheDados::lembrar(
                'contagem:'.$consulta->getModel()->getTable(),
                $dominios,
                [$base->toSql(), $base->getBindings()],
                fn () => $base->getCountForPagination(),
                $segundos,
            );
        };
    }
}
