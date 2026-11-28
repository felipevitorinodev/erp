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

        if ($this->boolean('sem_cnpj')) {
            $merge['cnpj'] = null;
        }

        // NFS-e: códigos só com dígitos (o autocompletar manda "14.01.01") e percentuais com vírgula.
        foreach (['nfse_c_trib_nac', 'nfse_c_trib_mun', 'nfse_nbs', 'nfse_class_trib', 'nfse_ind_op', 'nfse_serie'] as $campo) {
            if ($this->exists($campo)) {
                $digitos = preg_replace('/\D/', '', (string) $this->input($campo)) ?? '';
                $merge[$campo] = $digitos === '' ? null : ($campo === 'nfse_serie' ? (ltrim($digitos, '0') ?: '0') : $digitos);
            }
        }
        foreach (['nfse_aliquota_iss', 'nfse_ptot_trib_sn', 'nfse_ptot_trib_fed', 'nfse_ptot_trib_est', 'nfse_ptot_trib_mun'] as $campo) {
            if ($this->exists($campo)) {
                $valor = trim(str_replace(['%', ' '], '', (string) $this->input($campo)));
                if (str_contains($valor, ',')) {
                    $valor = str_replace(['.', ','], ['', '.'], $valor);
                }
                $merge[$campo] = $valor === '' ? null : $valor;
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
            'razao_social' => 'required|string|max:150',
            'nome_fantasia' => 'nullable|string|max:150',
            'sem_cnpj' => ['sometimes', 'boolean'],
            'cnpj' => $this->boolean('sem_cnpj')
                ? ['nullable', 'string', 'max:18']
                : ['required', 'string', 'max:18', 'cnpj'],
            'telefone' => 'nullable|string|max:20',
            'celular' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'cep' => 'nullable|string|max:9',
            'cidade' => 'nullable|string|max:100',
            'logradouro' => 'nullable|string|max:150',
            'numero' => 'nullable|string|max:20',
            'bairro' => 'nullable|string|max:100',
            'uf' => 'nullable|string|max:2',
            'ie' => 'nullable|string|max:20',
            'im' => 'nullable|string|max:20',
            'complemento' => 'nullable|string|max:100',
            'certificado_pfx' => 'nullable|file|extensions:pfx,p12|max:5120',
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
            'crt' => 'sometimes|required|integer|in:1,2,3,4',
            'nfe_destino' => 'sometimes|required|integer|in:1,2,3',
            'nfe_cfop_padrao' => 'sometimes|required|digits:4',
            'nfe_csosn_padrao' => 'sometimes|required|digits:3',
            'nfe_cst_pis_padrao' => 'sometimes|required|digits:2',
            'nfe_cst_cofins_padrao' => 'sometimes|required|digits:2',
            'nfse_ambiente' => 'sometimes|required|integer|in:1,2',
            'confirmar_ambiente_nfse' => 'nullable|boolean',
            'nfse_serie' => 'nullable|integer|between:1,49999',
            'nfse_regime_apuracao_sn' => 'nullable|integer|in:1,2,3',
            'nfse_regime_especial' => 'sometimes|required|integer|in:0,1,2,3,4,5,6,9',
            'nfse_aliquota_iss' => 'nullable|numeric|between:0,5',
            'nfse_ptot_trib_sn' => 'nullable|numeric|between:0,100',
            'nfse_ptot_trib_fed' => 'nullable|numeric|between:0,100',
            'nfse_ptot_trib_est' => 'nullable|numeric|between:0,100',
            'nfse_ptot_trib_mun' => 'nullable|numeric|between:0,100',
            'nfse_c_trib_nac' => 'nullable|digits:6|exists:nfse_servicos,codigo',
            'nfse_c_trib_mun' => 'nullable|digits:3',
            'nfse_nbs' => 'nullable|digits:9|exists:nfse_nbs,codigo',
            'nfse_class_trib' => 'nullable|digits:6|exists:nfse_classificacoes,codigo',
            'nfse_ind_op' => 'nullable|digits:6|exists:nfse_indicadores_operacao,codigo',
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

    public function messages(): array
    {
        return [
            'cnpj.required' => 'Informe o CNPJ ou marque Cadastrar sem CNPJ.',
            'certificado_pfx.max' => 'O certificado não pode ter mais de 5 MB.',
            'required' => 'O campo :attribute é obrigatório.',
            'date' => 'O campo :attribute deve ser uma data válida.',
            'numeric' => 'O campo :attribute deve ser numérico.',
            'min' => 'O campo :attribute deve ter no mínimo :min.',
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
            'nfse_ambiente.in' => 'O ambiente da NFS-e deve ser Produção restrita ou Produção.',
            'nfse_serie.between' => 'A série da DPS vai de 1 a 49999 (as séries acima são reservadas).',
            'nfse_serie.integer' => 'A série da DPS deve ter só números.',
            'nfse_aliquota_iss.between' => 'A alíquota do ISSQN vai de 0% a 5%.',
            'nfse_c_trib_nac.exists' => 'Código de tributação nacional não encontrado na tabela oficial.',
            'nfse_c_trib_nac.digits' => 'O código de tributação nacional tem 6 dígitos (ex.: 14.01.01).',
            'nfse_c_trib_mun.digits' => 'O código municipal tem 3 dígitos.',
            'nfse_nbs.exists' => 'NBS não encontrado na tabela oficial.',
            'nfse_nbs.digits' => 'O NBS tem 9 dígitos.',
            'nfse_class_trib.exists' => 'Classificação tributária não encontrada na tabela oficial.',
            'nfse_ind_op.exists' => 'Indicador da operação não encontrado na tabela oficial.',
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
