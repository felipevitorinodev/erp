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
            'itens.*.quantidade' => 'required|numeric|min:0.01',
            'itens.*.preco_unitario' => 'required|numeric|min:0',
            'itens.*.desconto' => 'nullable|numeric|min:0',
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

    protected function prepareForValidation(): void
    {
        $items = $this->input('itens', []);
        if (!is_array($items)) return;

        foreach ($items as $k => $it) {
            foreach (['quantidade', 'preco_unitario', 'desconto'] as $field) {
                if (!isset($it[$field])) continue;
                $val = (string) $it[$field];
                $val = trim($val);
                $val = str_replace(' ', '', $val);

                if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
                    $val = str_replace('.', '', $val);
                    $val = str_replace(',', '.', $val);
                } elseif (strpos($val, ',') !== false) {
                    $val = str_replace(',', '.', $val);
                } else {
                    // keep dots as decimal separator if present
                }

                $items[$k][$field] = $val;
            }
        }

        $this->merge(['itens' => $items]);
    }
}
