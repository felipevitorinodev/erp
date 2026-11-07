<?php

namespace App\Impressao;

use App\Models\Empresas\Empresa;

class EmpresaDocumento
{
    public function __construct(
        public string $nome,
        public string $documento,
        public string $endereco,
        public string $contato,
        public ?string $logoArquivo,
        public ?string $logoUrl,
    ) {}

    public static function atual(): self
    {
        $id = auth()->user()?->empresa_id;
        $empresa = $id ? Empresa::query()->find($id) : null;

        return self::de($empresa);
    }

    public static function de(?Empresa $empresa): self
    {
        $nome = trim((string) ($empresa?->nome_fantasia ?: $empresa?->razao_social ?: 'Visys'));
        $bruto = preg_replace('/\D/', '', (string) ($empresa?->cnpj ?: $empresa?->cpf ?: '')) ?? '';
        $documento = match (strlen($bruto)) {
            14 => 'CNPJ '.preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $bruto),
            11 => 'CPF '.preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $bruto),
            default => trim((string) ($empresa?->cnpj ?: $empresa?->cpf ?: '')),
        };

        $rua = trim(implode(', ', array_filter([
            $empresa?->logradouro,
            $empresa?->numero,
        ], fn ($parte) => filled($parte))));
        $endereco = trim(implode(' · ', array_filter([
            $rua,
            $empresa?->bairro,
        ], fn ($parte) => filled($parte))));
        $cidade = trim(implode('/', array_filter([
            $empresa?->cidade,
            $empresa?->uf,
        ], fn ($parte) => filled($parte))));
        $telefone = $empresa?->telefone ?: $empresa?->celular;
        $contato = trim(implode(' · ', array_filter([
            $cidade,
            $telefone,
        ], fn ($parte) => filled($parte))));

        $arquivo = public_path('img/logo-v-sem-fundo.png');
        $existe = is_file($arquivo);

        return new self(
            $nome,
            $documento,
            $endereco,
            $contato,
            $existe ? $arquivo : null,
            $existe ? asset('img/logo-v-sem-fundo.png') : null,
        );
    }

    /**
     * @return list<string>
     */
    public function linhas(): array
    {
        return array_values(array_filter([
            $this->documento,
            $this->endereco,
            $this->contato,
        ], fn ($linha) => $linha !== ''));
    }
}
