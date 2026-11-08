<?php

namespace App\Http\Requests\PerfisFiscais;

use App\Models\PerfisFiscais\PerfilFiscal;
use App\Services\Nfes\NfeBuilderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerfilFiscalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $perfil = $this->route('perfilFiscal');
        $ignorar = $perfil instanceof PerfilFiscal ? $perfil->id : null;
        $empresaId = (int) auth()->user()->empresa_id;

        $unico = function (string $coluna) use ($empresaId, $ignorar) {
            $regra = Rule::unique('perfis_fiscais', $coluna)
                ->where(fn ($query) => $query->where('empresa_id', $empresaId)->whereNull('deleted_at'));

            return $ignorar ? $regra->ignore($ignorar) : $regra;
        };

        return [
            'nome' => ['required', 'string', 'max:80', $unico('nome')],
            'operacao' => ['nullable', Rule::in(['venda', 'devolucao'])],
            'uf_destino' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'ncm' => ['nullable', 'digits:8'],
            'cfop' => ['required', 'digits:4', 'regex:/^5\d{3}$/', Rule::exists('cfops', 'codigo')],
            'csosn' => ['required', Rule::in(NfeBuilderService::CSOSN_SEM_CALCULO)],
            'cst_pis' => ['required', Rule::in(NfeBuilderService::CST_SEM_INCIDENCIA)],
            'cst_cofins' => ['required', Rule::in(NfeBuilderService::CST_SEM_INCIDENCIA)],
            'ativo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do perfil.',
            'nome.unique' => 'Já existe um perfil com este nome.',
            'ncm.digits' => 'O NCM do perfil deve ter 8 dígitos.',
            'operacao.in' => 'A operação do perfil é venda ou devolução.',
            'uf_destino.regex' => 'A UF de destino deve ter 2 letras.',
            'cfop.regex' => 'Nesta versão o CFOP precisa ser da série 5 (operação interna).',
            'cfop.exists' => 'Escolha um CFOP da tabela de operações internas.',
            'csosn.in' => 'Nesta versão o CSOSN aceito é 102, 103, 300 ou 400.',
            'cst_pis.in' => 'Nesta versão o CST de PIS aceito é de 04 a 09.',
            'cst_cofins.in' => 'Nesta versão o CST de COFINS aceito é de 04 a 09.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $digitos = static fn ($valor) => preg_replace('/\D/', '', (string) $valor) ?? '';

        $this->merge([
            'nome' => trim((string) $this->input('nome')),
            'operacao' => $this->input('operacao') ?: null,
            'uf_destino' => strtoupper(trim((string) $this->input('uf_destino'))) ?: null,
            'ncm' => $digitos($this->input('ncm')) ?: null,
            'cfop' => $digitos($this->input('cfop')),
            'csosn' => $digitos($this->input('csosn')),
            'cst_pis' => str_pad($digitos($this->input('cst_pis')), 2, '0', STR_PAD_LEFT),
            'cst_cofins' => str_pad($digitos($this->input('cst_cofins')), 2, '0', STR_PAD_LEFT),
            'ativo' => $this->boolean('ativo'),
        ]);
    }
}
