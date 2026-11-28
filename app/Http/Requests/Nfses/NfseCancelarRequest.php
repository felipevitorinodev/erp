<?php

namespace App\Http\Requests\Nfses;

use App\Models\Nfses\Nfse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NfseCancelarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo_motivo' => ['required', Rule::in(array_keys(Nfse::MOTIVOS_CANCELAMENTO))],
            'motivo' => ['required', 'string', 'min:15', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo_motivo' => 'motivo do cancelamento',
            'motivo' => 'justificativa',
        ];
    }
}
