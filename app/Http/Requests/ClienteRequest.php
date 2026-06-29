<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id'           => 'required|exists:empresa,id',

            'nome'                 => 'required|string|max:255',
            'nome_fantasia'        => 'nullable|string|max:255',

            'tipo_pessoa' => 'required|in:PF,PJ',
            'cpf'  => 'required_if:tipo_pessoa,PF|nullable|max:14|cpf',
            'cnpj' => 'required_if:tipo_pessoa,PJ|nullable|max:18|cnpj',

            'inscricao_estadual'   => 'nullable|string|max:20',
            'inscricao_municipal'  => 'nullable|string|max:20',

            'email'                => 'nullable|email|max:255',
            'telefone'             => 'nullable|string|max:20',
            'celular'              => 'nullable|string|max:20',

            'cep'                  => 'nullable|string|max:9',
            'logradouro'           => 'nullable|string|max:255',
            'numero'               => 'nullable|string|max:20',
            'complemento'          => 'nullable|string|max:255',
            'bairro'               => 'nullable|string|max:255',
            'cidade'               => 'nullable|string|max:100',
            'estado'               => 'nullable|string|size:2',

            'observacoes'          => 'nullable|string',
            'ativo'                => 'nullable|boolean',
        ];
    }
    public function messages(): array{
        return [
            'required' => 'O campo é obrigatório.',
            'max' => 'O campo precisa ter no máximo :max caracteres.',
        ];
    }
}