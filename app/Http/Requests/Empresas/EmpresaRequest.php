<?php

namespace App\Http\Requests\Empresas;

use App\Models\Empresas\Empresa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Throwable;

class EmpresaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $cep = preg_replace('/\D/', '', (string) $this->input('cep')) ?? '';
        $ibge = preg_replace('/\D/', '', (string) $this->input('cod_ibge')) ?? '';
        $merge = [];

        if (strlen($cep) === 8) {
            $merge['cep'] = substr($cep, 0, 5).'-'.substr($cep, 5);
        }

        if (strlen($ibge) === 7) {
            $merge['cod_ibge'] = $ibge;
        } elseif (strlen($cep) === 8) {
            $resolvido = $this->ibgePeloCep($cep);
            if ($resolvido !== null) {
                $merge['cod_ibge'] = $resolvido;
            }
        }

        foreach ([
            'nfce_serie',
            'nfce_csc_homologacao',
            'nfce_id_csc_homologacao',
            'nfce_csc_producao',
            'nfce_id_csc_producao',
        ] as $campo) {
            if ($this->exists($campo)) {
                $merge[$campo] = trim((string) $this->input($campo));
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
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
        'numero'       => 'nullable|string|max:20',
        'bairro'       => 'nullable|string|max:100',
        'uf'           => 'nullable|string|max:2',
        'ie'           => 'nullable|string|max:20',
        'im'           => 'nullable|string|max:20',
        'complemento'  => 'nullable|string|max:255',
        'certificado_pfx' => 'nullable|file|extensions:pfx,p12',
        'certificado_senha' => 'nullable|string|max:255',
        'nfe_ambiente' => 'sometimes|required|integer|in:1,2',
        'confirmar_ambiente' => 'nullable|boolean',
        'nfe_serie' => 'sometimes|required|string|max:3|regex:/^\d{1,3}$/',
        'nfce_serie' => 'nullable|string|max:3|regex:/^\d{1,3}$/',
        'nfce_csc_homologacao' => 'nullable|string|max:36|regex:/^[A-Za-z0-9-]*$/',
        'nfce_id_csc_homologacao' => 'nullable|string|max:6|regex:/^\d{0,6}$/',
        'nfce_csc_producao' => 'nullable|string|max:36|regex:/^[A-Za-z0-9-]*$/',
        'nfce_id_csc_producao' => 'nullable|string|max:6|regex:/^\d{0,6}$/',
        'nfce_emitir_pdv' => 'nullable|boolean',
        'cod_ibge' => [
            'nullable',
            'digits:7',
            Rule::requiredIf(fn () => $this->enderecoExigeIbge()),
        ],
        'crt' => 'sometimes|required|integer|in:1,2,3',
        'nfe_destino' => 'sometimes|required|integer|in:1,2,3',
        'nfe_cfop_padrao' => 'sometimes|required|digits:4',
        'nfe_csosn_padrao' => 'sometimes|required|digits:3',
        'nfe_cst_pis_padrao' => 'sometimes|required|digits:2',
        'nfe_cst_cofins_padrao' => 'sometimes|required|digits:2',
    ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $empresa = $this->route('empresa');
            if (! $empresa instanceof Empresa) {
                $empresa = null;
            }

            foreach ([
                'homologacao' => 'homologação',
                'producao' => 'produção',
            ] as $ambiente => $rotulo) {
                $csc = trim((string) $this->input('nfce_csc_'.$ambiente, ''));
                $id = trim((string) $this->input('nfce_id_csc_'.$ambiente, ''));
                $cscGravado = $empresa !== null && filled($empresa->{'nfce_csc_'.$ambiente});
                $idGravado = $empresa !== null && filled($empresa->{'nfce_id_csc_'.$ambiente});

                if ($csc !== '' && $id === '' && ! $idGravado) {
                    $validator->errors()->add(
                        'nfce_id_csc_'.$ambiente,
                        'Informe o idCSC de '.$rotulo.' junto com o CSC.'
                    );
                }

                if ($id !== '' && $csc === '' && ! $cscGravado) {
                    $validator->errors()->add(
                        'nfce_csc_'.$ambiente,
                        'Informe o CSC de '.$rotulo.' junto com o idCSC.'
                    );
                }
            }
        });
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
            'nfce_serie.regex' => 'A série da NFC-e deve ter de 1 a 3 dígitos.',
            'nfce_csc_homologacao.regex' => 'O CSC de homologação só pode ter letras, números e hífen.',
            'nfce_id_csc_homologacao.regex' => 'O idCSC de homologação deve ter de 1 a 6 dígitos.',
            'nfce_csc_producao.regex' => 'O CSC de produção só pode ter letras, números e hífen.',
            'nfce_id_csc_producao.regex' => 'O idCSC de produção deve ter de 1 a 6 dígitos.',
            'cod_ibge.digits' => 'O código IBGE deve ter 7 dígitos.',
            'cod_ibge.required' => 'Informe o código IBGE do município. Ele é preenchido ao consultar o CEP.',
            'nfe_cfop_padrao.digits' => 'O CFOP padrão deve ter 4 dígitos.',
            'nfe_csosn_padrao.digits' => 'O CSOSN padrão deve ter 3 dígitos.',
            'nfe_cst_pis_padrao.digits' => 'O CST de PIS deve ter 2 dígitos.',
            'nfe_cst_cofins_padrao.digits' => 'O CST de COFINS deve ter 2 dígitos.',
        ];
    }

    private function enderecoExigeIbge(): bool
    {
        $cep = preg_replace('/\D/', '', (string) $this->input('cep')) ?? '';
        $cidade = trim((string) $this->input('cidade'));
        $uf = trim((string) $this->input('uf'));

        return strlen($cep) === 8 || ($cidade !== '' && strlen($uf) === 2);
    }

    private function ibgePeloCep(string $cep): ?string
    {
        try {
            $resposta = Http::timeout(3)->acceptJson()->get('https://viacep.com.br/ws/'.$cep.'/json/');
            if (! $resposta->ok()) {
                return null;
            }

            $ibge = preg_replace('/\D/', '', (string) $resposta->json('ibge')) ?? '';

            return strlen($ibge) === 7 ? $ibge : null;
        } catch (Throwable) {
            return null;
        }
    }
}
