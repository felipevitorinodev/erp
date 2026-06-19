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
        return false;
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
            'tipo_pessoa' => 'required|integer|in:1,2',
            'cnpj' => 'cnpj',
            'cpf' => 'cpf',
            'email' => 'required|email|max:255',
            'numero' => 'integer',
            'uf' => 'string|max:2'
        ];
    }
}
