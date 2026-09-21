<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ContaReceberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'descricao'       => 'required|string|max:200',
            'cliente_id'      => 'nullable|exists:clientes,id',
            'venda_id'        => 'nullable|exists:vendas,id',
            'valor'           => 'required',
            'data_vencimento' => 'required|date',
            'forma_pagamento' => 'nullable|string|max:100',
            'observacoes'     => 'nullable|string',
        ];

        if ($this->isMethod('post')) {
            $rules['data_vencimento'] .= '|after_or_equal:today';
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $valor = $this->parseMoeda($this->input('valor'));

            if ($valor <= 0) {
                $validator->errors()->add('valor', 'O campo valor deve ser maior que zero.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'date'     => 'O campo :attribute deve ser uma data válida.',
            'numeric'  => 'O campo :attribute deve ser numérico.',
            'min'      => 'O campo :attribute deve ter no mínimo :min.',
            'data_vencimento.after_or_equal' => 'A data de vencimento não pode ser anterior a hoje.',
        ];
    }

    public function attributes(): array
    {
        return [
            'descricao'       => 'descrição',
            'cliente_id'      => 'cliente',
            'venda_id'        => 'venda',
            'valor'           => 'valor',
            'data_vencimento' => 'data de vencimento',
            'forma_pagamento' => 'forma de pagamento',
            'observacoes'     => 'observações',
        ];
    }

    protected function parseMoeda(mixed $valor): float
    {
        if (is_null($valor) || $valor === '') {
            return 0.0;
        }

        $str = trim((string) $valor);

        if (str_contains($str, ',') && str_contains($str, '.')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } elseif (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
        }

        return round((float) $str, 2);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('valor')) {
            $v = (string) $this->input('valor');
            $v = trim($v);
            $v = str_replace(' ', '', $v);

            if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } elseif (strpos($v, ',') !== false) {
                $v = str_replace(',', '.', $v);
            } else {
                // keep dot as decimal if present
            }

            $this->merge(['valor' => $v]);
        }
    }
}
