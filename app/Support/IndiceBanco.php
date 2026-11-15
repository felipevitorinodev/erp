<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Indica ao MySQL o índice certo (USE INDEX) em consultas por período.
 *
 * Medido com 1 milhão de vendas: para "vendas da empresa entre duas datas" o otimizador escolhia o índice
 * (empresa_id, nfe_rascunho, situacao), que não filtra data, e lia todas as vendas da empresa linha a linha
 * (totais do ano: 2,2 s; com o índice de relatório: 0,38 s). A dica só é aplicada se o índice existir
 * (migration já rodada) e só no MySQL; no SQLite dos testes não muda nada.
 */
final class IndiceBanco
{
    /** @var array<string, bool> */
    private static array $existentes = [];

    /**
     * @template T of EloquentBuilder|QueryBuilder
     *
     * @param  T  $consulta
     * @return T
     */
    public static function usar(EloquentBuilder|QueryBuilder $consulta, string $tabela, string $indice): EloquentBuilder|QueryBuilder
    {
        if (self::existe($consulta->getConnection(), $tabela, $indice)) {
            $consulta->useIndex($indice);
        }

        return $consulta;
    }

    /**
     * Tabela para join() já com a dica: `vendas` as `v` use index (`indice`). Sem MySQL ou sem o índice, só "tabela as apelido".
     */
    public static function tabela(EloquentBuilder|QueryBuilder $consulta, string $tabela, string $apelido, string $indice): Expression|string
    {
        if (! self::existe($consulta->getConnection(), $tabela, $indice)) {
            return $tabela.' as '.$apelido;
        }

        $gramatica = ($consulta instanceof EloquentBuilder ? $consulta->getQuery() : $consulta)->getGrammar();

        return new Expression($gramatica->wrapTable($tabela.' as '.$apelido).' use index ('.$gramatica->wrap($indice).')');
    }

    /**
     * Remove a dica (ex.: lotes buscados pela chave primária, em que o índice de período só atrapalharia).
     */
    public static function remover(EloquentBuilder|QueryBuilder $consulta): EloquentBuilder|QueryBuilder
    {
        $base = $consulta instanceof EloquentBuilder ? $consulta->getQuery() : $consulta;
        $base->indexHint = null;

        return $consulta;
    }

    private static function existe(ConnectionInterface $conexao, string $tabela, string $indice): bool
    {
        if (! in_array($conexao->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        $chave = $conexao->getName().'.'.$tabela.'.'.$indice;

        return self::$existentes[$chave] ??= (function () use ($conexao, $tabela, $indice, $chave): bool {
            try {
                return (bool) Cache::remember('indice-banco:'.$chave, 600, fn () => Schema::connection($conexao->getName())->hasIndex($tabela, $indice));
            } catch (Throwable $e) {
                report($e);

                return false;
            }
        })();
    }
}
