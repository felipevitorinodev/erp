<?php

namespace App\Http\Requests\Clientes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Cadastro rápido de cliente (balcão, NF-e e comandos): só o essencial para identificar o cliente.
 */
class ClienteRapidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:200',
            'cpf' => ['nullable', 'string', 'max:14', 'cpf', $this->unicoNaEmpresa('cpf')],
            'cnpj' => ['nullable', 'string', 'max:18', 'cnpj', $this->unicoNaEmpresa('cnpj')],
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do cliente é obrigatório.',
            'nome.max' => 'O nome do cliente pode ter no máximo :max caracteres.',
            'cpf.cpf' => 'CPF inválido.',
            'cnpj.cnpj' => 'CNPJ inválido.',
            'cpf.unique' => 'Já existe um cliente com este CPF.',
            'cnpj.unique' => 'Já existe um cliente com este CNPJ.',
            'email.email' => 'E-mail inválido.',
            'telefone.max' => 'Telefone com dígitos demais.',
        ];
    }

    private function unicoNaEmpresa(string $coluna): Unique
    {
        return Rule::unique('clientes', $coluna)
            ->where('empresa_id', auth()->user()->empresa_id)
            ->whereNull('deleted_at');
    }
}
