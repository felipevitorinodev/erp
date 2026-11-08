<?php

namespace App\Support;

use App\Models\Clientes\Cliente;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;

/**
 * Lista de primeiros passos da tela inicial para empresa recém-criada.
 * Cada passo só aparece para quem tem a permissão da rotina; a lista some quando tudo foi feito.
 */
final class PrimeirosPassos
{
    /**
     * Passos que o usuário pode fazer; vazio quando nenhum está pendente.
     *
     * @return list<array{titulo: string, texto: string, url: string, acao: string, feito: bool}>
     */
    public static function para(User $usuario): array
    {
        $empresaId = (int) $usuario->empresa_id;
        if ($empresaId === 0) {
            return [];
        }

        $possiveis = [
            [
                'permissao' => 'produtos',
                'titulo' => 'Cadastre seus produtos',
                'texto' => 'Com preço de venda e estoque inicial.',
                'url' => route('produto.create'),
                'acao' => 'Novo produto',
                'feito' => fn () => Produto::where('empresa_id', $empresaId)->exists(),
            ],
            [
                'permissao' => 'formas_pagamento',
                'titulo' => 'Defina as formas de pagamento',
                'texto' => 'Dinheiro, Pix, cartão e as que forem a prazo.',
                'url' => route('formaPagamento.create'),
                'acao' => 'Nova forma',
                'feito' => fn () => FormaPagamento::where('empresa_id', $empresaId)->where('ativo', true)->exists(),
            ],
            [
                'permissao' => 'clientes',
                'titulo' => 'Cadastre seus clientes',
                'texto' => 'Para vender a prazo e emitir nota com CPF ou CNPJ.',
                'url' => route('cliente.create'),
                'acao' => 'Novo cliente',
                'feito' => fn () => Cliente::where('empresa_id', $empresaId)->exists(),
            ],
            [
                'permissao' => 'vendas',
                'titulo' => 'Faça a primeira venda',
                'texto' => 'Pelo PDV, com leitor de código de barras ou busca.',
                'url' => route('pdv.index'),
                'acao' => 'Abrir PDV',
                'feito' => fn () => Venda::where('empresa_id', $empresaId)->exists(),
            ],
        ];

        $passos = [];
        foreach ($possiveis as $passo) {
            if (! $usuario->pode($passo['permissao'])) {
                continue;
            }
            $passo['feito'] = ($passo['feito'])();
            unset($passo['permissao']);
            $passos[] = $passo;
        }

        $pendentes = array_filter($passos, fn (array $p) => ! $p['feito']);

        return $pendentes === [] ? [] : $passos;
    }
}
