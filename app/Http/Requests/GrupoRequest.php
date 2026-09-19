<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
         return [
            'empresa_id' => 'required|exists:empresa,id',
            'parent_id'  => 'nullable|exists:categorias,id',
            'nome'       => 'required|string|max:255',
            'descricao'  => 'nullable|string',
            'ativo'      => 'nullable|boolean',
        ];
    }
    public function messages(): array{
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'date'     => 'O campo :attribute deve ser uma data válida.',
            'numeric'  => 'O campo :attribute deve ser numérico.',
            'min'      => 'O campo :attribute deve ter no mínimo :min.',
            'max' => 'O campo :attribute precisa ter no máximo :max caracteres.',
        ];
    }
}