<?php

namespace App\Http\Requests\Grupos;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $grupoAtual = $this->route('grupo');
        $grupoAtualId = is_object($grupoAtual) ? $grupoAtual->getKey() : $grupoAtual;

         return [
            'empresa_id' => 'required|exists:empresa,id',
            'parent_id'  => [
                'nullable',
                Rule::exists('grupos', 'id')
                    ->where('empresa_id', auth()->user()->empresa_id)
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at'),
                Rule::notIn(array_filter([$grupoAtualId])),
            ],
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