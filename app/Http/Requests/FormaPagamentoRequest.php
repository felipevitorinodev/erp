<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FormaPagamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id'         => 'required|exists:empresa,id',
            'nome'               => 'required|string|max:100',
            'tipo'               => 'required|in:avista,prazo',
            'gera_conta_receber' => 'nullable|boolean',
            'dias_vencimento'    => 'nullable|integer|min:0|max:3650',
            'ativo'              => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'date'     => 'O campo :attribute deve ser uma data válida.',
            'numeric'  => 'O campo :attribute deve ser numérico.',
            'min'      => 'O campo :attribute deve ter no mínimo :min.',
            'max'      => 'O campo :attribute precisa ter no máximo :max caracteres.',
            'in'       => 'O campo :attribute é inválido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nome'            => 'nome',
            'tipo'            => 'tipo',
            'dias_vencimento' => 'dias para vencimento',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tipo = $this->input('tipo', 'avista');
        $ehPrazo = $tipo === 'prazo';

        $this->merge([
            'tipo'               => $tipo,
            'gera_conta_receber' => $ehPrazo,
            'dias_vencimento'    => $ehPrazo ? (int) ($this->input('dias_vencimento') ?: 30) : 0,
            'ativo'              => $this->has('ativo') ? (bool) $this->input('ativo') : true,
        ]);
    }
}
