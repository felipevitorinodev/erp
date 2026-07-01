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
            'empresa_id'           => 'required|exists:empresa,id',
            'nome'                 => 'required|string|max:255',
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