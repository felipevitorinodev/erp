<?php

namespace App\Http\Requests\Fornecedores;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro rápido de fornecedor (cadastro de produto e comandos): só o essencial.
 */
class FornecedorRapidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:200',
            'cnpj' => 'nullable|string|max:18|cnpj',
            'telefone' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do fornecedor é obrigatório.',
            'nome.max' => 'O nome do fornecedor pode ter no máximo :max caracteres.',
            'cnpj.cnpj' => 'CNPJ inválido.',
            'telefone.max' => 'Telefone com dígitos demais.',
        ];
    }
}
