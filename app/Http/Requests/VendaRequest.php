<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\Funcionario;
use App\Models\Produto;
use App\Traits\ConversorMoeda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VendaRequest extends FormRequest
{
    use ConversorMoeda;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id'      => 'nullable|exists:clientes,id',
            'funcionario_id'  => 'nullable|exists:funcionarios,id',
            'data_venda'      => 'required|date',
            'data_entrega'    => 'nullable|date',
            'forma_pagamento' => 'nullable|string|max:100',
            'situacao'        => 'nullable|in:em_andamento,confirmada',
            'desconto'        => 'nullable|numeric|min:0',
            'acrescimo'       => 'nullable|numeric|min:0',
            'observacoes'     => 'nullable|string',
            'itens'           => 'required|array|min:1',
            'itens.*.produto_id'     => 'required|exists:produtos,id',
            'itens.*.quantidade'     => 'required|numeric|min:0.001',
            'itens.*.preco_unitario' => 'required|numeric|min:0',
            'itens.*.desconto'       => 'nullable|numeric|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresaId = (int) auth()->user()->empresa_id;

            if ($this->filled('cliente_id')) {
                $ok = Cliente::where('id', $this->input('cliente_id'))
                    ->where('empresa_id', $empresaId)
                    ->exists();
                if (!$ok) {
                    $validator->errors()->add('cliente_id', 'Cliente inválido para esta empresa.');
                }
            }

            if ($this->filled('funcionario_id')) {
                $ok = Funcionario::where('id', $this->input('funcionario_id'))
                    ->where('empresa_id', $empresaId)
                    ->where('ativo', true)
                    ->exists();
                if (!$ok) {
                    $validator->errors()->add('funcionario_id', 'Vendedor inválido para esta empresa.');
                }
            }

            foreach ($this->input('itens', []) as $idx => $item) {
                if (empty($item['produto_id'])) {
                    continue;
                }
                $ok = Produto::where('id', $item['produto_id'])
                    ->where('empresa_id', $empresaId)
                    ->exists();
                if (!$ok) {
                    $validator->errors()->add('itens.' . $idx . '.produto_id', 'Produto inválido para esta empresa.');
                }
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
            'itens.required' => 'Adicione pelo menos um item.',
            'itens.min'      => 'Adicione pelo menos um item.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_id'      => 'cliente',
            'funcionario_id'  => 'vendedor',
            'data_venda'      => 'data da venda',
            'data_entrega'    => 'data de entrega',
            'forma_pagamento' => 'forma de pagamento',
            'desconto'        => 'desconto',
            'acrescimo'       => 'acréscimo',
            'itens'           => 'itens',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('itens', []);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $k => $it) {
            foreach (['quantidade', 'preco_unitario', 'desconto'] as $field) {
                if (!isset($it[$field])) {
                    continue;
                }
                $items[$k][$field] = $this->normalizarNumero((string) $it[$field]);
            }
        }

        $merge = ['itens' => $items];
        foreach (['desconto', 'acrescimo', 'subtotal', 'total'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = $this->normalizarNumero((string) $this->input($field));
            }
        }

        $this->merge($merge);
    }

    private function normalizarNumero(string $val): string
    {
        $val = trim(str_replace(' ', '', $val));

        if (str_contains($val, ',') && str_contains($val, '.')) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } elseif (str_contains($val, ',')) {
            $val = str_replace(',', '.', $val);
        }

        return $val;
    }
}
