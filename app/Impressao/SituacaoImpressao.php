<?php

namespace App\Impressao;

class SituacaoImpressao
{
    public static function classe(string $texto): ?string
    {
        $chave = mb_strtolower(trim($texto));

        return match ($chave) {
            'paga', 'pago', 'confirmada', 'confirmado', 'aprovado', 'aprovada',
            'concluída', 'concluida', 'entregue', 'faturada', 'recebido', 'recebida' => 'ok',
            'a receber', 'em aberto', 'em andamento', 'pendente', 'parcial',
            'aberta', 'em execução', 'em execucao' => 'wait',
            'vencido', 'vencida', 'cancelada', 'cancelado', 'recusado', 'recusada',
            'aguardando peça', 'aguardando peca' => 'mute',
            default => null,
        };
    }
}
