<?php

namespace App\Comandos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Valida dados com o mesmo FormRequest da tela: prepareForValidation, regras, mensagens e withValidator.
 * Assim o chat nunca aceita algo que o formulário recusaria.
 */
final class FormularioInterno
{
    /**
     * @template T of FormRequest
     *
     * @param  class-string<T>  $classe
     * @return T
     *
     * @throws ValidationException
     */
    public static function validar(string $classe, array $dados): FormRequest
    {
        $origem = request();

        $formulario = $classe::create($origem->url(), 'POST', $dados);
        $formulario->setContainer(app())->setRedirector(app('redirect'));
        $formulario->setUserResolver($origem->getUserResolver());
        $formulario->setRouteResolver($origem->getRouteResolver());
        $formulario->validateResolved();

        return $formulario;
    }
}
