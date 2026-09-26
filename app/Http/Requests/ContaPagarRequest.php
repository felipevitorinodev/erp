<?php

namespace App\Http\Requests;

use App\Models\CategoriaFinanceira;
use App\Traits\ConversorMoeda;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ContaPagarRequest extends FormRequest
{
    use ConversorMoeda;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'descricao'       => 'required|string|max:200',
            'fornecedor_id'   => 'nullable|exists:fornecedores,id',
            'valor'           => 'required',
            'data_vencimento' => 'required|date',
            'forma_pagamento' => 'nullable|string|max:100',
            'categoria_id'    => 'nullable|exists:categorias_financeiras,id',
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

            if ($this->filled('categoria_id')) {
                $empresaId = (int) auth()->user()->empresa_id;
                $valida = CategoriaFinanceira::where('id', $this->input('categoria_id'))
                    ->where('empresa_id', $empresaId)
                    ->where('tipo', 'despesa')
                    ->where('ativo', true)
                    ->exists();

                if (!$valida) {
                    $validator->errors()->add('categoria_id', 'Categoria inválida para conta a pagar.');
                }
            }

            $empresaId = (int) auth()->user()->empresa_id;

            if ($this->filled('fornecedor_id')) {
                $fornecedorOk = \App\Models\Fornecedor::where('id', $this->input('fornecedor_id'))
                    ->where('empresa_id', $empresaId)
                    ->exists();
                if (!$fornecedorOk) {
                    $validator->errors()->add('fornecedor_id', 'Fornecedor inválido para esta empresa.');
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
            'data_vencimento.after_or_equal' => 'A data de vencimento não pode ser anterior a hoje.',
        ];
    }

    public function attributes(): array
    {
        return [
            'descricao'       => 'descrição',
            'fornecedor_id'   => 'fornecedor',
            'valor'           => 'valor',
            'data_vencimento' => 'data de vencimento',
            'forma_pagamento' => 'forma de pagamento',
            'categoria_id'    => 'categoria',
            'observacoes'     => 'observações',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('categoria_id')) {
            $this->merge(['categoria_id' => null]);
        }

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
