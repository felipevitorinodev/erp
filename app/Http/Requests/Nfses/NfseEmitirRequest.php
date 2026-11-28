<?php

namespace App\Http\Requests\Nfses;

use Illuminate\Foundation\Http\FormRequest;

class NfseEmitirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalizar($this));
    }

    /**
     * Campos da NFS-e só com dígitos (o autocompletar manda o rótulo inteiro) e a alíquota com ponto.
     * Também usado pela tela "Nova NFS-e" (NfseNovaRequest).
     *
     * @return array<string, mixed>
     */
    public static function normalizar(FormRequest $request): array
    {
        $limpar = fn (string $campo) => preg_replace('/\D/', '', (string) $request->input($campo, '')) ?? '';

        return [
            'c_trib_nac' => $limpar('c_trib_nac'),
            'c_trib_mun' => $limpar('c_trib_mun'),
            'nbs' => $limpar('nbs'),
            'class_trib' => $limpar('class_trib'),
            'ind_op' => $limpar('ind_op'),
            'municipio_prestacao' => $limpar('municipio_prestacao'),
            'aliquota_iss' => str_replace(',', '.', trim((string) $request->input('aliquota_iss', ''))),
            'iss_retido' => $request->boolean('iss_retido'),
            'memorizar' => $request->boolean('memorizar'),
        ];
    }

    public function rules(): array
    {
        return self::regras();
    }

    /**
     * @return array<string, list<string>>
     */
    public static function regras(): array
    {
        return [
            'c_trib_nac' => ['required', 'digits:6'],
            'c_trib_mun' => ['nullable', 'digits:3'],
            'nbs' => ['required', 'digits:9'],
            'class_trib' => ['required', 'digits:6'],
            'ind_op' => ['nullable', 'digits:6'],
            'municipio_prestacao' => ['required', 'digits:7'],
            'descricao' => ['required', 'string', 'max:2000'],
            'iss_retido' => ['boolean'],
            'aliquota_iss' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'data_competencia' => ['required', 'date', 'before_or_equal:today'],
            'memorizar' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return self::atributos();
    }

    /**
     * @return array<string, string>
     */
    public static function atributos(): array
    {
        return [
            'c_trib_nac' => 'código de tributação nacional',
            'c_trib_mun' => 'código de tributação municipal',
            'nbs' => 'código NBS',
            'class_trib' => 'classificação tributária (cClassTrib)',
            'ind_op' => 'indicador da operação',
            'municipio_prestacao' => 'município da prestação',
            'descricao' => 'descrição do serviço',
            'aliquota_iss' => 'alíquota do ISSQN',
            'data_competencia' => 'competência',
        ];
    }

    public function messages(): array
    {
        return self::mensagens();
    }

    /**
     * @return array<string, string>
     */
    public static function mensagens(): array
    {
        return [
            'c_trib_nac.required' => 'Escolha o código de tributação nacional na lista.',
            'c_trib_nac.digits' => 'Escolha o código de tributação nacional na lista.',
            'nbs.required' => 'Escolha o código NBS na lista.',
            'nbs.digits' => 'Escolha o código NBS na lista.',
            'class_trib.required' => 'Escolha a classificação tributária do IBS/CBS na lista.',
            'class_trib.digits' => 'Escolha a classificação tributária do IBS/CBS na lista.',
            'municipio_prestacao.required' => 'Escolha o município da prestação na lista.',
            'municipio_prestacao.digits' => 'Escolha o município da prestação na lista.',
            'data_competencia.before_or_equal' => 'A competência não pode ser futura.',
            'aliquota_iss.max' => 'A alíquota do ISSQN não pode passar de 5%.',
        ];
    }
}
