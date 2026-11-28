<?php

namespace App\Http\Requests\Produtos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class ProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|exists:empresa,id',
            'grupo_id' => ['nullable', $this->existeNaEmpresa('grupos')],
            'unidade_medida_id' => ['nullable', $this->existeNaEmpresa('unidades_medida')],
            'fornecedor_id' => ['nullable', $this->existeNaEmpresa('fornecedores')],
            'perfil_fiscal_id' => ['nullable', $this->existeNaEmpresa('perfis_fiscais')],

            // Os excluídos contam: o índice único (empresa_id, codigo) também os considera.
            'codigo' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('produtos', 'codigo')
                    ->where('empresa_id', auth()->user()->empresa_id)
                    ->ignore($this->route('produto')),
            ],
            'codigo_barras' => 'nullable|string|max:50',
            'referencia' => 'nullable|string|max:50',
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'tipo' => 'required|in:1,2,3',

            'preco_custo' => 'nullable|numeric|min:0',
            'preco_venda' => 'nullable|numeric|min:0',
            'preco_minimo' => ['nullable', 'numeric', 'min:0', function (string $attribute, mixed $value, \Closure $fail) {
                $precoVenda = $this->input('preco_venda');
                if (is_numeric($value) && is_numeric($precoVenda) && $precoVenda > 0 && $value > $precoVenda) {
                    $fail('O preço mínimo não pode ser maior que o preço de venda.');
                }
            }],
            'margem_lucro' => 'nullable|numeric',

            'estoque_minimo' => 'nullable|numeric|min:0',
            'estoque_maximo' => 'nullable|numeric|min:0',
            'estoque_atual' => 'nullable|numeric|min:0',

            'peso' => 'nullable|numeric|min:0',
            'altura' => 'nullable|numeric|min:0',
            'largura' => 'nullable|numeric|min:0',
            'profundidade' => 'nullable|numeric|min:0',

            'ncm' => 'nullable|string|max:10',
            'cest' => 'nullable|string|max:10',
            'cfop_padrao' => 'nullable|string|max:5',
            'origem' => 'nullable|integer',
            'cst_icms' => 'nullable|string|max:5',
            'cst_pis' => 'nullable|string|max:3',
            'cst_cofins' => 'nullable|string|max:3',
            'aliquota_icms' => 'nullable|numeric',
            'aliquota_pis' => 'nullable|numeric',
            'aliquota_cofins' => 'nullable|numeric',

            // NFS-e: códigos do serviço, sugeridos na emissão e gravados por ela.
            'nfse_c_trib_nac' => 'nullable|digits:6|exists:nfse_servicos,codigo',
            'nfse_c_trib_mun' => 'nullable|digits:3',
            'nfse_nbs' => 'nullable|digits:9|exists:nfse_nbs,codigo',
            'nfse_class_trib' => 'nullable|digits:6|exists:nfse_classificacoes,codigo',
            'nfse_ind_op' => 'nullable|digits:6|exists:nfse_indicadores_operacao,codigo',

            'controla_estoque' => 'nullable|boolean',
            'ativo' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'date' => 'O campo :attribute deve ser uma data válida.',
            'numeric' => 'O campo :attribute deve ser numérico.',
            'min' => 'O campo :attribute deve ter no mínimo :min.',
            'max' => 'O campo :attribute precisa ter no máximo :max caracteres.',
            'codigo.unique' => 'Já existe um produto com este código, ativo ou excluído.',
            'nfse_c_trib_nac.digits' => 'O código de tributação nacional tem 6 dígitos (ex.: 14.01.01).',
            'nfse_c_trib_nac.exists' => 'Código de tributação nacional não encontrado na tabela oficial.',
            'nfse_c_trib_mun.digits' => 'O código municipal tem 3 dígitos.',
            'nfse_nbs.digits' => 'O NBS tem 9 dígitos.',
            'nfse_nbs.exists' => 'NBS não encontrado na tabela oficial.',
            'nfse_class_trib.exists' => 'Classificação tributária não encontrada na tabela oficial.',
            'nfse_ind_op.exists' => 'Indicador da operação não encontrado na tabela oficial.',
        ];
    }

    private function existeNaEmpresa(string $tabela): Exists
    {
        return Rule::exists($tabela, 'id')
            ->where('empresa_id', auth()->user()->empresa_id)
            ->whereNull('deleted_at');
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'preco_custo', 'preco_venda', 'preco_minimo', 'margem_lucro',
            'estoque_minimo', 'estoque_maximo', 'estoque_atual',
            'peso', 'altura', 'largura', 'profundidade',
            'aliquota_icms', 'aliquota_pis', 'aliquota_cofins',
        ];

        if ($this->input('perfil_fiscal_id') === '') {
            $this->merge(['perfil_fiscal_id' => null]);
        }

        foreach (['nfse_c_trib_nac', 'nfse_c_trib_mun', 'nfse_nbs', 'nfse_class_trib', 'nfse_ind_op'] as $campo) {
            if ($this->exists($campo)) {
                $digitos = preg_replace('/\D/', '', (string) $this->input($campo)) ?? '';
                $this->merge([$campo => $digitos === '' ? null : $digitos]);
            }
        }

        foreach ($fields as $f) {
            if ($this->filled($f)) {
                $v = (string) $this->input($f);
                $v = trim($v);
                $v = str_replace(' ', '', $v);
                // Se contém ponto e vírgula -> assume ponto = milhares, vírgula = decimal
                if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
                    $v = str_replace('.', '', $v);
                    $v = str_replace(',', '.', $v);
                } elseif (strpos($v, ',') !== false) {
                    // apenas vírgula -> decimal
                    $v = str_replace(',', '.', $v);
                } else {
                    // apenas ponto ou nenhum separador -> manter ponto como decimal (não remover)
                    // nada a fazer
                }
                $this->merge([$f => $v]);
            }
        }
    }
}
