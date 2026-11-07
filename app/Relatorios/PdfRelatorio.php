<?php

namespace App\Relatorios;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfRelatorio
{
    public const LIMITE = 1000;

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function stream(string $arquivo, array $dados, string $orientacao = 'portrait'): Response
    {
        return Pdf::loadView('pdf.relatorio', $dados)
            ->setPaper('a4', $orientacao)
            ->stream($arquivo);
    }

    public static function aviso(int $total): ?string
    {
        if ($total <= self::LIMITE) {
            return null;
        }

        return 'Exibindo '.self::LIMITE.' de '.$total.' registros (exporte em CSV para a lista completa)';
    }
}
