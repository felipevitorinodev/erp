<?php

namespace App\Http\Requests\Estoque;

use App\Models\Fornecedores\Fornecedor;
use App\Models\Produtos\Produto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PedidoCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fornecedor_id' => ['required', 'integer'],
            'data_emissao' => ['required', 'date'],
            'data_prevista' => ['nullable', 'date', 'after_or_equal:data_emissao'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['required', 'integer', 'distinct'],
            'itens.*.quantidade' => ['required', 'numeric', 'min:0.001'],
            'itens.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'itens.*.desconto' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $empresaId = (int) auth()->user()->empresa_id;
            $fornecedorValido = Fornecedor::whereKey($this->input('fornecedor_id'))
                ->where('empresa_id', $empresaId)
                ->exists();

            if (! $fornecedorValido) {
                $validator->errors()->add('fornecedor_id', 'Fornecedor inválido para esta empresa.');
            }

            $subtotal = 0.0;
            foreach ($this->input('itens', []) as $idx => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $produto = Produto::whereKey($item['produto_id'] ?? null)
                    ->where('empresa_id', $empresaId)
                    ->first();

                if (! $produto) {
                    $validator->errors()->add('itens.'.$idx.'.produto_id', 'Produto inválido para esta empresa.');

                    continue;
                }

                $bruto = (float) ($item['quantidade'] ?? 0) * (float) ($item['preco_unitario'] ?? 0);
                $desconto = (float) ($item['desconto'] ?? 0);
                if ($desconto > $bruto) {
                    $validator->errors()->add('itens.'.$idx.'.desconto', 'O desconto não pode ser maior que o total do item.');
                }
                $subtotal += max(0, $bruto - $desconto);
            }

            if ((float) $this->input('desconto', 0) > $subtotal) {
                $validator->errors()->add('desconto', 'O desconto não pode ser maior que o subtotal.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $itens = $this->input('itens', []);
        if (is_array($itens)) {
            foreach ($itens as $indice => $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach (['quantidade', 'preco_unitario', 'desconto'] as $campo) {
                    if (isset($item[$campo])) {
                        $itens[$indice][$campo] = $this->normalizarNumero((string) $item[$campo]);
                    }
                }
            }
        }

        $dados = ['itens' => $itens];
        foreach (['desconto'] as $campo) {
            if ($this->filled($campo)) {
                $dados[$campo] = $this->normalizarNumero((string) $this->input($campo));
            }
        }

        $this->merge($dados);
    }

    public function attributes(): array
    {
        return [
            'fornecedor_id' => 'fornecedor',
            'data_emissao' => 'data de emissão',
            'data_prevista' => 'previsão de entrega',
            'desconto' => 'desconto',
            'observacoes' => 'observações',
            'itens' => 'itens',
            'itens.*.produto_id' => 'produto',
            'itens.*.quantidade' => 'quantidade',
            'itens.*.preco_unitario' => 'preço unitário',
        ];
    }

    private function normalizarNumero(string $valor): string
    {
        $valor = trim(str_replace(' ', '', $valor));

        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            return str_replace(',', '.', str_replace('.', '', $valor));
        }

        return str_replace(',', '.', $valor);
    }
}
