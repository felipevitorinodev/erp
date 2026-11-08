<?php

namespace App\Comandos;

use RuntimeException;

/** Dado digitado que não dá para usar (data que não existe, CPF com dígitos faltando...). */
final class EntradaInvalida extends RuntimeException
{
    /**
     * @param  string|null  $trecho  o pedaço da frase com o problema, como foi digitado (a resposta do usuário o substitui)
     */
    public function __construct(string $mensagem, public readonly ?string $trecho = null)
    {
        parent::__construct($mensagem);
    }
}
