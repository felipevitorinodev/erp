<?php

namespace App\Support;

use Throwable;

class MensagemPublica
{
    public static function de(
        Throwable $e,
        string $reserva = 'Não foi possível concluir a operação. Tente novamente.'
    ): string {
        $mensagem = trim($e->getMessage());

        if ($mensagem === '' || self::interna($mensagem)) {
            report($e);

            return $reserva;
        }

        return $mensagem;
    }

    private static function interna(string $mensagem): bool
    {
        return (bool) preg_match(
            '/SQLSTATE|PDOException|Stack trace|Illuminate\\\\|syntax error|Base table or view not found|vendor[\/\\\\]/i',
            $mensagem
        );
    }
}
