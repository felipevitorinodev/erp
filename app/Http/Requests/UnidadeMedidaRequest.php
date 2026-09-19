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
            'required' => 'O campo :attribute é obrigatório.',
            'date'     => 'O campo :attribute deve ser uma data válida.',
            'numeric'  => 'O campo :attribute deve ser numérico.',
            'min'      => 'O campo :attribute deve ter no mínimo :min.',
            'max' => 'O campo :attribute precisa ter no máximo :max caracteres.',
        ];
    }
}