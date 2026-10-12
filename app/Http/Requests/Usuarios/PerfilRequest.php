<?php

namespace App\Http\Requests\Usuarios;

use App\Support\CatalogoPermissoes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->pode('configuracoes');
    }

    public function rules(): array
    {
        $perfil = $this->route('perfilAcesso');
        $admin = $perfil && $perfil->slug === 'admin';

        return [
            'nome' => 'required|string|max:100',
            'ativo' => 'nullable|boolean',
            'permissoes' => $admin ? 'nullable|array' : 'required|array|min:1',
            'permissoes.*' => ['string', Rule::in(CatalogoPermissoes::chaves())],
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome',
            'permissoes' => 'permissões',
        ];
    }

    public function messages(): array
    {
        return [
            'permissoes.required' => 'Marque ao menos uma permissão.',
            'permissoes.min' => 'Marque ao menos uma permissão.',
        ];
    }
}
