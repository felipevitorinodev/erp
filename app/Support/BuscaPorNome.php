<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filtro "nome da pessoa contém o termo" para listagens grandes.
 *
 * Substitui orWhereHas('cliente', nome LIKE ...), que vira EXISTS correlacionado (uma subconsulta por linha
 * da venda/conta), por cliente_id IN (SELECT id ... WHERE empresa_id = ? AND nome LIKE ?): o MySQL
 * materializa a lista de pessoas uma única vez e só faz busca em hash por linha. Mesmo resultado: a pessoa
 * excluída continua fora (escopo de exclusão lógica do modelo) e a pessoa é sempre da mesma empresa.
 */
final class BuscaPorNome
{
    /**
     * @param  class-string<Model>  $modelo
     */
    public static function ids(string $modelo, int $empresaId, string $termo, string $coluna = 'nome'): Builder
    {
        return $modelo::query()
            ->select('id')
            ->where('empresa_id', $empresaId)
            ->where($coluna, 'like', '%'.$termo.'%');
    }
}
