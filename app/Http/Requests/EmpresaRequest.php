<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EmpresaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
        'razao_social' => 'required|string|max:255',
        'nome_fantasia' => 'nullable|string|max:255',
        'cnpj'         => 'required|string|max:18|cnpj',
        'telefone'     => 'nullable|string|max:20',
        'email'        => 'nullable|email|max:255',
        'cep'          => 'nullable|string|max:9',
        'cidade'       => 'nullable|string|max:100',
        'logradouro'   => 'nullable|string|max:255',
        'uf'           => 'nullable|string|max:2',
        'ie'           => 'nullable|string|max:20',
        'im'           => 'nullable|string|max:20',
        'complemento'  => 'nullable|string|max:255'
    ];
    }

    public function messages(): array{
        return [
            'required' => 'Campo obrigatório.',
            'max' => 'O campo precisa ter no máximo :max caracteres.',
        ];
    }
}
