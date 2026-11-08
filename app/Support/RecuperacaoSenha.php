<?php

namespace App\Support;

/**
 * "Esqueci a senha" só funciona com um mailer de verdade. Em produção com MAIL_MAILER=log o e-mail
 * nunca sai e o link com o token iria para o log, então a tela some até o SMTP ser configurado.
 */
class RecuperacaoSenha
{
    public const MENSAGEM_INDISPONIVEL = 'A recuperação de senha por e-mail não está disponível. Peça ao administrador para redefinir sua senha.';

    public static function disponivel(): bool
    {
        return config('mail.default') !== 'log' || ! app()->isProduction();
    }
}
