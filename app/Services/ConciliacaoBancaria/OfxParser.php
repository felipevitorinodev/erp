<?php

namespace App\Services\ConciliacaoBancaria;

use Illuminate\Validation\ValidationException;

class OfxParser
{
    /**
     * @return array{
     *     conta_bancaria:?string,
     *     data_inicio:?string,
     *     data_fim:?string,
     *     transacoes:array<int, array{fingerprint:string,fitid:?string,data_transacao:string,tipo:?string,valor:float,descricao:string,memo:?string}>
     * }
     */
    public function parse(string $conteudo): array
    {
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo) ?? $conteudo;
        preg_match_all('/<STMTTRN\b[^>]*>(.*?)<\/STMTTRN>/is', $conteudo, $blocos);

        if ($blocos[1] === []) {
            throw ValidationException::withMessages([
                'arquivo' => 'O arquivo OFX não contém transações bancárias.',
            ]);
        }

        $contaBancaria = $this->campo($conteudo, 'ACCTID');
        $banco = $this->campo($conteudo, 'BANKID');
        $chaveConta = implode('|', array_filter([$banco, $contaBancaria]));

        $transacoes = [];
        foreach ($blocos[1] as $indice => $bloco) {
            $data = $this->data($this->campo($bloco, 'DTPOSTED'), $indice + 1);
            $valorTexto = $this->campo($bloco, 'TRNAMT');

            if ($valorTexto === null || ! preg_match('/^-?\d+(?:\.\d+)?$/', $valorTexto)) {
                throw ValidationException::withMessages([
                    'arquivo' => 'O arquivo OFX contém uma transação com valor inválido.',
                ]);
            }

            $valor = (float) $valorTexto;
            $fitid = $this->campo($bloco, 'FITID');
            $tipo = $this->campo($bloco, 'TRNTYPE');
            $descricao = $this->campo($bloco, 'NAME') ?? '';
            $memo = $this->campo($bloco, 'MEMO');
            $identificador = implode('|', [
                $chaveConta,
                $fitid ?? '',
                $data,
                number_format($valor, 2, '.', ''),
                $tipo ?? '',
                $descricao,
                $memo ?? '',
            ]);

            $transacoes[] = [
                'fingerprint' => hash('sha256', $identificador),
                'fitid' => $fitid,
                'data_transacao' => $data,
                'tipo' => $tipo,
                'valor' => $valor,
                'descricao' => $descricao,
                'memo' => $memo,
            ];
        }

        return [
            'conta_bancaria' => $contaBancaria,
            'data_inicio' => $this->dataOpcional($this->campo($conteudo, 'DTSTART')),
            'data_fim' => $this->dataOpcional($this->campo($conteudo, 'DTEND')),
            'transacoes' => $transacoes,
        ];
    }

    private function campo(string $conteudo, string $nome): ?string
    {
        $nome = preg_quote($nome, '/');
        if (! preg_match('/<'.$nome.'(?:\s[^>]*)?>(.*?)(?:<\/'.$nome.'>|(?=<))/is', $conteudo, $match)) {
            return null;
        }

        $valor = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));

        return $valor === '' ? null : $valor;
    }

    private function data(?string $valor, int $indice): string
    {
        $data = $this->dataOpcional($valor);
        if ($data === null) {
            throw ValidationException::withMessages([
                'arquivo' => 'O arquivo OFX contém uma transação sem data válida (linha '.$indice.').',
            ]);
        }

        return $data;
    }

    private function dataOpcional(?string $valor): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $valor);
        if (strlen($digits) < 8) {
            return null;
        }

        $ano = (int) substr($digits, 0, 4);
        $mes = (int) substr($digits, 4, 2);
        $dia = (int) substr($digits, 6, 2);

        if (! checkdate($mes, $dia, $ano)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
    }
}
