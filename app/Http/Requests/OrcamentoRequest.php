<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrcamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => 'nullable|exists:clientes,id',
            'data_orcamento' => 'required|date',
            'itens'      => 'required|array|min:1',
            'itens.*.produto_id' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'date'     => 'O campo :attribute deve ser uma data válida.',
            'numeric'  => 'O campo :attribute deve ser numérico.',
            'min'      => 'O campo :attribute deve ter no mínimo :min.',
            'itens.required' => 'Adicione pelo menos um item.',
            'itens.min'      => 'Adicione pelo menos um item.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_id'     => 'cliente',
            'data_orcamento' => 'data do orçamento',
            'itens'          => 'itens',
        ];
    }
}
