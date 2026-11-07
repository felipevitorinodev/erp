<?php

namespace App\Http\Requests\Usuarios;

use App\Models\Usuarios\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth()->user()?->pode('configuracoes');
    }

    public function rules(): array
    {
        /** @var User|null $usuario */
        $usuario = $this->route('usuario');

        return [
            'name'     => 'required|string|max:255',
            'email'    => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($usuario?->id),
            ],
            'password' => $this->isMethod('post')
                ? 'required|string|min:6|confirmed'
                : 'nullable|string|min:6|confirmed',
            'perfil'   => [
                'required',
                'string',
                Rule::exists('perfis', 'slug')->where(
                    fn ($query) => $query
                        ->where('empresa_id', auth()->user()->empresa_id)
                        ->where('ativo', true)
                ),
            ],
            'ativo'    => 'nullable|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (auth()->user()->isAdmin()) {
                return;
            }

            /** @var User|null $alvo */
            $alvo = $this->route('usuario');

            if ($this->input('perfil') === 'admin' || $alvo?->isAdmin()) {
                $validator->errors()->add('perfil', 'Somente administradores podem criar ou alterar administradores.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name'     => 'nome',
            'email'    => 'e-mail',
            'password' => 'senha',
            'perfil'   => 'perfil',
        ];
    }
}
