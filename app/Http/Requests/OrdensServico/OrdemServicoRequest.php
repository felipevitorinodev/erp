<?php

namespace App\Http\Requests\OrdensServico;

use App\Models\Clientes\Cliente;
use App\Models\Funcionarios\Funcionario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OrdemServicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id'        => 'nullable|exists:clientes,id',
            'funcionario_id'    => 'nullable|exists:funcionarios,id',
            'data_abertura'     => 'required|date',
            'data_previsao'     => 'nullable|date|after_or_equal:data_abertura',
            'equipamento'       => 'required|string|max:150',
            'marca'             => 'nullable|string|max:80',
            'modelo'            => 'nullable|string|max:80',
            'numero_serie'      => 'nullable|string|max:80',
            'acessorios'        => 'nullable|string|max:255',
            'defeito_relatado'  => 'nullable|string|max:5000',
            'diagnostico'       => 'nullable|string|max:5000',
            'servico_executado' => 'nullable|string|max:5000',
            'garantia'          => 'nullable|string|max:100',
            'forma_pagamento'   => 'nullable|string|max:100',
            'observacoes'       => 'nullable|string|max:5000',
            'itens'             => 'nullable|array',
            'itens.*.produto_id' => 'nullable',
            'itens.*.quantidade' => 'required|numeric|min:0.001',
            'itens.*.preco_unitario' => 'required|numeric|min:0',
            'itens.*.desconto'   => 'nullable|numeric|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresaId = auth()->user()->empresa_id;

            if ($this->filled('cliente_id')
                && ! Cliente::where('id', $this->input('cliente_id'))->where('empresa_id', $empresaId)->exists()) {
                $validator->errors()->add('cliente_id', 'Cliente inválido para esta empresa.');
            }

            if ($this->filled('funcionario_id')
                && ! Funcionario::where('id', $this->input('funcionario_id'))->where('empresa_id', $empresaId)->exists()) {
                $validator->errors()->add('funcionario_id', 'Técnico inválido para esta empresa.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'required'       => 'O campo :attribute é obrigatório.',
            'date'           => 'O campo :attribute deve ser uma data válida.',
            'numeric'        => 'O campo :attribute deve ser numérico.',
            'min'            => 'O campo :attribute deve ter no mínimo :min.',
            'max'            => 'O campo :attribute deve ter no máximo :max caracteres.',
            'after_or_equal' => 'A previsão não pode ser anterior à abertura.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_id'        => 'cliente',
            'funcionario_id'    => 'técnico',
            'data_abertura'     => 'data de abertura',
            'data_previsao'     => 'previsão',
            'equipamento'       => 'equipamento',
            'numero_serie'      => 'nº de série',
            'defeito_relatado'  => 'defeito relatado',
            'servico_executado' => 'serviço executado',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('itens', []);
        if (! is_array($items)) {
            return;
        }

        foreach ($items as $k => $it) {
            foreach (['quantidade', 'preco_unitario', 'desconto'] as $field) {
                if (! isset($it[$field])) {
                    continue;
                }
                $val = str_replace(' ', '', trim((string) $it[$field]));

                if (str_contains($val, ',') && str_contains($val, '.')) {
                    $val = str_replace(['.', ','], ['', '.'], $val);
                } elseif (str_contains($val, ',')) {
                    $val = str_replace(',', '.', $val);
                }

                $items[$k][$field] = $val;
            }
        }

        $this->merge(['itens' => $items]);
    }
}
