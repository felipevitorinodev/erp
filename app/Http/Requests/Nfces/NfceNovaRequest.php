<?php

namespace App\Http\Requests\Nfces;

use App\Http\Requests\Vendas\VendaRequest;
use App\Models\Produtos\Produto;
use App\Services\Vendas\DivisaoFiscalVenda;
use Illuminate\Validation\Validator;

/**
 * Tela "Nova NFC-e": as regras da venda, com a venda sempre confirmada e só com produtos
 * (serviço não entra em NFC-e; vai na NFS-e).
 */
class NfceNovaRequest extends VendaRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(['situacao' => 'confirmada']);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator) {
            $itens = $this->input('itens', []);
            if (! is_array($itens)) {
                return;
            }

            $produtos = Produto::query()
                ->where('empresa_id', auth()->user()->empresa_id)
                ->whereIn('id', array_filter(array_map(fn ($item) => (int) (is_array($item) ? ($item['produto_id'] ?? 0) : 0), $itens)))
                ->get(['id', 'nome', 'tipo'])
                ->keyBy('id');

            foreach ($itens as $indice => $item) {
                $produto = is_array($item) ? $produtos->get((int) ($item['produto_id'] ?? 0)) : null;
                if ($produto && (int) ($produto->tipo ?? 1) === DivisaoFiscalVenda::TIPO_SERVICO) {
                    $validator->errors()->add(
                        'itens.'.$indice.'.produto_id',
                        '"'.$produto->nome.'" é serviço. A NFC-e só leva produtos; emita a NFS-e para os serviços.',
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'nfce_documento.max' => 'O CPF ou CNPJ tem no máximo 18 caracteres.',
        ]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'nfce_documento' => 'CPF ou CNPJ do consumidor',
        ]);
    }
}
