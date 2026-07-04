<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id'         => 'required|exists:empresa,id',
            'grupo_id'           => 'nullable|exists:grupos,id',
            'unidade_medida_id'  => 'nullable|exists:unidades_medida,id',
            'fornecedor_id'      => 'nullable|exists:fornecedores,id',

            'codigo'             => 'nullable|string|max:50',
            'codigo_barras'      => 'nullable|string|max:50',
            'referencia'         => 'nullable|string|max:50',
            'nome'               => 'required|string|max:255',
            'descricao'          => 'nullable|string',
            'tipo'               => 'required|in:1,2,3',

            'preco_custo'        => 'nullable|numeric|min:0',
            'preco_venda'        => 'nullable|numeric|min:0',
            'preco_minimo'       => 'nullable|numeric|min:0',
            'margem_lucro'       => 'nullable|numeric',

            'estoque_minimo'     => 'nullable|numeric|min:0',
            'estoque_maximo'     => 'nullable|numeric|min:0',
            'estoque_atual'      => 'nullable|numeric|min:0',

            'peso'               => 'nullable|numeric|min:0',
            'altura'             => 'nullable|numeric|min:0',
            'largura'            => 'nullable|numeric|min:0',
            'profundidade'       => 'nullable|numeric|min:0',

            'ncm'                => 'nullable|string|max:10',
            'cest'               => 'nullable|string|max:10',
            'cfop_padrao'        => 'nullable|string|max:5',
            'origem'             => 'nullable|integer',
            'cst_icms'           => 'nullable|string|max:5',
            'cst_pis'            => 'nullable|string|max:3',
            'cst_cofins'         => 'nullable|string|max:3',
            'aliquota_icms'      => 'nullable|numeric',
            'aliquota_pis'       => 'nullable|numeric',
            'aliquota_cofins'    => 'nullable|numeric',

            'controla_estoque'          => 'nullable|boolean',
            'permite_venda_sem_estoque' => 'nullable|boolean',
            'ativo'              => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo é obrigatório.',
            'max' => 'O campo precisa ter no máximo :max caracteres.',
        ];
    }
}