<?php

namespace App\Services;

use App\Models\CategoriaFinanceira;

class CategoriaPadraoService
{
    public const VENDAS = 'Vendas';
    public const COMPRAS = 'Compras / Estoque';
    public const COMISSOES = 'Comissões';

    public function idReceitaVendas(int $empresaId): ?int
    {
        return $this->obterOuCriar($empresaId, self::VENDAS, 'receita')?->id;
    }

    public function idDespesaCompras(int $empresaId): ?int
    {
        return $this->obterOuCriar($empresaId, self::COMPRAS, 'despesa')?->id;
    }

    public function idDespesaComissoes(int $empresaId): ?int
    {
        return $this->obterOuCriar($empresaId, self::COMISSOES, 'despesa')?->id;
    }

    public function obterOuCriar(int $empresaId, string $nome, string $tipo): ?CategoriaFinanceira
    {
        if ($empresaId <= 0 || !in_array($tipo, ['receita', 'despesa'], true)) {
            return null;
        }

        return CategoriaFinanceira::firstOrCreate(
            [
                'empresa_id' => $empresaId,
                'nome'       => $nome,
                'tipo'       => $tipo,
            ],
            ['ativo' => true]
        );
    }
}
