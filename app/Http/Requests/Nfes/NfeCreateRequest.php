<?php

namespace App\Http\Requests\Nfes;

use App\Http\Requests\Vendas\VendaRequest;

class NfeCreateRequest extends VendaRequest
{
    public function rules(): array
    {
        // Rascunho aceita dados fiscais incompletos; o formato continua validado quando informado.
        $obrigatorio = $this->ehRascunho() ? 'nullable' : 'required';

        $regras = parent::rules();
        $regras['acao'] = 'nullable|in:rascunho,emitir';
        $regras['forma_pagamento'] = $obrigatorio.'|string|max:100';
        $regras['situacao'] = 'nullable|in:confirmada';
        $regras['natureza_operacao'] = $obrigatorio.'|string|max:60';
        $regras['mod_frete'] = 'required|integer|in:0,1,2,3,4,9';
        $regras['ind_final'] = 'nullable|integer|in:0,1';
        $regras['inf_ad_fisco'] = 'nullable|string|max:2000';
        $regras['itens.*.ncm'] = $obrigatorio.'|digits:8';
        $regras['itens.*.cfop'] = $obrigatorio.'|digits:4';
        $regras['itens.*.csosn'] = $obrigatorio.'|digits:3';
        $regras['itens.*.cst_pis'] = $obrigatorio.'|digits:2';
        $regras['itens.*.cst_cofins'] = $obrigatorio.'|digits:2';
        $regras['itens.*.origem'] = $obrigatorio.'|integer|between:0,8';
        $regras['itens.*.unidade'] = $obrigatorio.'|string|max:6';
        $regras['itens.*.cest'] = 'nullable|digits_between:7,9';
        $regras['itens.*.ean'] = 'nullable|string|max:14';

        return $regras;
    }

    public function ehRascunho(): bool
    {
        return $this->input('acao') === 'rascunho';
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'forma_pagamento.required' => 'Selecione a forma de pagamento da NF-e.',
            'natureza_operacao.required' => 'Informe a natureza da operação.',
            'itens.*.ncm.required' => 'Informe o NCM de cada item.',
            'itens.*.ncm.digits' => 'O NCM deve ter 8 dígitos.',
            'itens.*.cfop.required' => 'Informe o CFOP de cada item.',
            'itens.*.csosn.required' => 'Informe o CSOSN de cada item.',
            'itens.*.cst_pis.required' => 'Informe o CST de PIS de cada item.',
            'itens.*.cst_cofins.required' => 'Informe o CST de COFINS de cada item.',
        ]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'natureza_operacao' => 'natureza da operação',
            'mod_frete' => 'modalidade do frete',
            'itens.*.ncm' => 'NCM',
            'itens.*.cfop' => 'CFOP',
            'itens.*.csosn' => 'CSOSN',
            'itens.*.cst_pis' => 'CST PIS',
            'itens.*.cst_cofins' => 'CST COFINS',
            'itens.*.origem' => 'origem',
            'itens.*.unidade' => 'unidade',
        ]);
    }
}
