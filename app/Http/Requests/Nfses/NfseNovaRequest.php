<?php

namespace App\Http\Requests\Nfses;

use App\Http\Requests\Vendas\VendaRequest;
use App\Models\Produtos\Produto;
use App\Services\Vendas\DivisaoFiscalVenda;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * Tela "Nova NFS-e": grava a venda confirmada só com serviços e emite a NFS-e com os dados escolhidos.
 * Regras da venda (VendaRequest) mais as da emissão (NfseEmitirRequest); a descrição em branco vira a lista dos serviços.
 */
class NfseNovaRequest extends VendaRequest
{
    public const CAMPOS_NFSE = [
        'c_trib_nac', 'c_trib_mun', 'nbs', 'class_trib', 'ind_op', 'municipio_prestacao',
        'descricao', 'iss_retido', 'aliquota_iss', 'data_competencia', 'memorizar',
    ];

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(NfseEmitirRequest::normalizar($this) + ['situacao' => 'confirmada']);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), NfseEmitirRequest::regras(), [
            'descricao' => ['nullable', 'string', 'max:2000'],
        ]);
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
                if ($produto && (int) ($produto->tipo ?? 1) !== DivisaoFiscalVenda::TIPO_SERVICO) {
                    $validator->errors()->add(
                        'itens.'.$indice.'.produto_id',
                        '"'.$produto->nome.'" está cadastrado como produto. A NFS-e só leva serviços; os produtos vão na NF-e ou na NFC-e.',
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), NfseEmitirRequest::mensagens(), [
            'itens.required' => 'Adicione pelo menos um serviço.',
            'itens.min' => 'Adicione pelo menos um serviço.',
        ]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), NfseEmitirRequest::atributos());
    }

    /**
     * Só os campos da NFS-e, no formato que o NfseService::emitir recebe.
     *
     * @return array<string, mixed>
     */
    public function entradaNfse(): array
    {
        return Arr::only($this->validated(), self::CAMPOS_NFSE) + ['descricao' => ''];
    }
}
