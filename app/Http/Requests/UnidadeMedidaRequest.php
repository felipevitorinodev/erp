<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnidadeMedidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|exists:empresa,id',
            'nome'       => 'required|string|max:255',
            'sigla'      => 'required|string|max:10',
            'ativo'      => 'nullable|boolean',
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