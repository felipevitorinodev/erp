<?php

namespace App\Relatorios;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvRelatorio
{
    /**
     * @param  list<string|int|float|null>  $campos
     * @return list<string|int|float|null>
     */
    public static function seguro(array $campos): array
    {
        return array_map(
            fn ($valor) => is_string($valor) && preg_match('/^[=+\-@\t\r]/', $valor) && ! preg_match('/^-?[\d.,]+$/', $valor)
                ? "'".$valor
                : $valor,
            $campos
        );
    }

    /**
     * @param  list<string>  $cabecalho
     * @param  iterable<int, list<string|int|float|null>>  $linhas
     */
    public static function stream(string $arquivo, array $cabecalho, iterable $linhas): StreamedResponse
    {
        return response()->streamDownload(function () use ($cabecalho, $linhas) {
            $saida = fopen('php://output', 'w');
            fprintf($saida, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($saida, $cabecalho, ';');

            foreach ($linhas as $linha) {
                fputcsv($saida, self::seguro($linha), ';');
            }

            fclose($saida);
        }, $arquivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
