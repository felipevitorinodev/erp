<?php

namespace App\Http\Requests;

use App\Models\Fornecedor;
use App\Models\Produto;
use App\Traits\ConversorMoeda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EntradaEstoqueRequest extends FormRequest
{
    use ConversorMoeda;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fornecedor_id' => 'nullable|exists:fornecedores,id',
            'numero'        => 'required|string|max:20',
            'data_entrada'  => 'required|date',
            'desconto'      => 'nullable|numeric|min:0',
            'observacoes'   => 'nullable|string',
            'itens'         => 'required|array|min:1',
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

            if ($this->filled('fornecedor_id')) {
                $ok = Fornecedor::where('id', $this->input('fornecedor_id'))
                    ->where('empresa_id', $empresaId)
                    ->exists();
                if (!$ok) {
                    $validator->errors()->add('fornecedor_id', 'Fornecedor inválido para esta empresa.');
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

    public function attributes(): array
    {
        return [
            'fornecedor_id' => 'fornecedor',
            'numero'        => 'número da NF',
            'data_entrada'  => 'data de entrada',
            'desconto'      => 'desconto',
            'observacoes'   => 'observações',
            'itens'         => 'itens',
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
        foreach (['desconto', 'subtotal', 'total'] as $field) {
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
