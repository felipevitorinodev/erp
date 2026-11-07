<?php

namespace App\Support;

use App\Models\OrdensServico\OrdemServico;

/**
 * Rótulo e cor (classe de badge) de cada situação exibida nas telas.
 * A chave "padrao" cobre valores fora da lista, como as telas já faziam no @else.
 */
class Situacoes
{
    private const MAPAS = [
        'venda' => [
            'confirmada' => ['Confirmada', 'success'],
            'cancelada' => ['Cancelada', 'error'],
            'padrao' => ['Em andamento', 'warning'],
        ],
        'conta' => [
            'paga' => ['Paga', 'success'],
            'parcial' => ['Parcial', 'info'],
            'cancelada' => ['Cancelada', 'error'],
            'padrao' => ['Aberta', 'warning'],
        ],
        'orcamento' => [
            'aprovado' => ['Aprovado', 'success'],
            'recusado' => ['Recusado', 'error'],
            'cancelado' => ['Cancelado', 'error'],
            'padrao' => ['Pendente', 'warning'],
        ],
        'entrada' => [
            'confirmada' => ['Confirmada', 'success'],
            'cancelada' => ['Cancelada', 'error'],
            'padrao' => ['Rascunho', 'warning'],
        ],
        'nfe' => [
            'autorizada' => ['Autorizada', 'success'],
            'rejeitada' => ['Rejeitada', 'error'],
            'denegada' => ['Denegada', 'error'],
            'cancelada' => ['Cancelada', 'neutral'],
            'padrao' => ['Pendente', 'warning'],
        ],
        'nfe_ambiente' => [
            'producao' => ['Produção', 'success'],
            'padrao' => ['Homologação', 'warning'],
        ],
        'comissao' => [
            'paga' => ['Paga', 'success'],
            'padrao' => ['Pendente', 'warning'],
        ],
        'movimento' => [
            'entrada' => ['Entrada', 'success'],
            'saida' => ['Saída', 'error'],
            'padrao' => ['Ajuste', 'info'],
        ],
        'ordem_servico' => [
            'aberta' => [OrdemServico::SITUACOES['aberta'], 'neutral'],
            'em_execucao' => [OrdemServico::SITUACOES['em_execucao'], 'info'],
            'aguardando_peca' => [OrdemServico::SITUACOES['aguardando_peca'], 'warning'],
            'concluida' => [OrdemServico::SITUACOES['concluida'], 'success'],
            'faturada' => [OrdemServico::SITUACOES['faturada'], 'success'],
            'cancelada' => [OrdemServico::SITUACOES['cancelada'], 'error'],
        ],
    ];

    /** @return array{0: string, 1: string} [rótulo, tom] */
    public static function de(string $tipo, ?string $valor): array
    {
        $mapa = self::MAPAS[$tipo] ?? throw new \InvalidArgumentException("Tipo de situação desconhecido: {$tipo}");

        return $mapa[(string) $valor] ?? $mapa['padrao'] ?? [(string) $valor, 'neutral'];
    }
}
