<?php

namespace App\Http\Requests\Estoque;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportarXmlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'xml_file' => 'required|file|max:2048',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $arquivo = $this->file('xml_file');

            if (!$arquivo) {
                return;
            }

            $extensao = strtolower($arquivo->getClientOriginalExtension());

            if ($extensao !== 'xml') {
                $validator->errors()->add('xml_file', 'O arquivo deve ter extensão .xml');
            }
        });
    }

    public function messages(): array
    {
        return [
            'xml_file.required' => 'Selecione um arquivo XML.',
            'xml_file.file'     => 'O arquivo enviado é inválido.',
            'xml_file.max'      => 'O arquivo não pode ter mais de 2MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'xml_file' => 'arquivo XML',
        ];
    }
}
