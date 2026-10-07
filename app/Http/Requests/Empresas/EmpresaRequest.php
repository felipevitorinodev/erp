<?php

namespace App\Http\Requests\Empresas;

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
        'complemento'  => 'nullable|string|max:255',
        'certificado_pfx' => 'nullable|file|extensions:pfx,p12',
        'certificado_senha' => 'nullable|string|max:255',
        'nfe_ambiente' => 'sometimes|required|integer|in:1,2',
        'nfe_serie' => 'sometimes|required|string|max:3|regex:/^\d{1,3}$/',
        'cod_ibge' => 'nullable|digits:7',
        'crt' => 'sometimes|required|integer|in:1,2,3',
        'nfe_destino' => 'sometimes|required|integer|in:1,2,3',
        'nfe_cfop_padrao' => 'sometimes|required|digits:4',
        'nfe_csosn_padrao' => 'sometimes|required|digits:3',
        'nfe_cst_pis_padrao' => 'sometimes|required|digits:2',
        'nfe_cst_cofins_padrao' => 'sometimes|required|digits:2',
    ];
    }

    public function messages(): array{
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'date'     => 'O campo :attribute deve ser uma data válida.',
            'numeric'  => 'O campo :attribute deve ser numérico.',
            'min'      => 'O campo :attribute deve ter no mínimo :min.',
            'max' => 'O campo :attribute precisa ter no máximo :max caracteres.',
            'nfe_ambiente.in' => 'O ambiente da NF-e deve ser Homologação ou Produção.',
            'nfe_serie.regex' => 'A série da NF-e deve ter de 1 a 3 dígitos.',
            'cod_ibge.digits' => 'O código IBGE deve ter 7 dígitos.',
            'nfe_cfop_padrao.digits' => 'O CFOP padrão deve ter 4 dígitos.',
            'nfe_csosn_padrao.digits' => 'O CSOSN padrão deve ter 3 dígitos.',
            'nfe_cst_pis_padrao.digits' => 'O CST de PIS deve ter 2 dígitos.',
            'nfe_cst_cofins_padrao.digits' => 'O CST de COFINS deve ter 2 dígitos.',
        ];
    }
}
