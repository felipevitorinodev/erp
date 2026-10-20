<?php

namespace App\Http\Requests\Clientes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class ClienteRequest extends FormRequest
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
            'nome_fantasia'        => 'nullable|string|max:255',

            'tipo_pessoa' => 'required|in:PF,PJ',
            'cpf'  => ['nullable', 'max:14', 'cpf', $this->unicoNaEmpresa('cpf')],
            'cnpj' => ['required_if:tipo_pessoa,PJ', 'nullable', 'max:18', 'cnpj', $this->unicoNaEmpresa('cnpj')],

            'inscricao_estadual'   => 'nullable|string|max:20',
            'inscricao_municipal'  => 'nullable|string|max:20',

            'email'                => 'nullable|email|max:255',
            'telefone'             => 'nullable|string|max:20',
            'celular'              => 'nullable|string|max:20',

            'cep'                  => 'nullable|string|max:9',
            'logradouro'           => 'nullable|string|max:255',
            'numero'               => 'nullable|string|max:20',
            'complemento'          => 'nullable|string|max:255',
            'bairro'               => 'nullable|string|max:255',
            'cidade'               => 'nullable|string|max:100',
            'estado'               => 'nullable|string|size:2',

            'observacoes'          => 'nullable|string',
            'ativo'                => 'nullable|boolean',
        ];
    }
    private function unicoNaEmpresa(string $coluna): Unique
    {
        $cliente = $this->route('cliente');

        return Rule::unique('clientes', $coluna)
            ->where('empresa_id', auth()->user()->empresa_id)
            ->whereNull('deleted_at')
            ->ignore(is_object($cliente) ? $cliente->getKey() : $cliente);
    }

    public function messages(): array{
        return [
            'cpf.unique'  => 'Já existe um cliente com este CPF.',
            'cnpj.unique' => 'Já existe um cliente com este CNPJ.',
            'required' => 'O campo :attribute é obrigatório.',
            'date'     => 'O campo :attribute deve ser uma data válida.',
            'numeric'  => 'O campo :attribute deve ser numérico.',
            'min'      => 'O campo :attribute deve ter no mínimo :min.',
            'required_if' => 'O campo :attribute é obrigatório.',
            'max' => 'O campo :attribute precisa ter no máximo :max caracteres.',
        ];
    }
}