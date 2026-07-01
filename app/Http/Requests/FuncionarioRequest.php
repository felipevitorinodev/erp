<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FuncionarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id'      => 'required|exists:empresa,id',
            'usuario_id'      => 'nullable|exists:users,id',

            'nome'            => 'required|string|max:255',
            'cpf'             => 'nullable|max:14|cpf',
            'rg'              => 'nullable|string|max:20',
            'data_nascimento' => 'nullable|date',

            'email'           => 'nullable|email|max:255',
            'telefone'        => 'nullable|string|max:20',
            'celular'         => 'nullable|string|max:20',

            'cargo'           => 'nullable|string|max:100',
            'departamento'    => 'nullable|string|max:100',
            'salario'         => 'nullable|numeric|min:0',

            'data_admissao'   => 'nullable|date',
            'data_demissao'   => 'nullable|date|after_or_equal:data_admissao',
            'tipo_contrato'   => 'nullable|in:CLT,PJ,Autonomo,Estagio',

            'ativo'           => 'nullable|boolean',
            'observacoes'     => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo é obrigatório.',
            'max' => 'O campo precisa ter no máximo :max caracteres.',
        ];
    }
}