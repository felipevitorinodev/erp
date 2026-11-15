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
        return Pdf::loadHTML(self::compactar(view('pdf.relatorio', $dados)->render()))
            ->setPaper('a4', $orientacao)
            ->stream($arquivo);
    }

    /**
     * Tira os espaços em branco em volta das tags de tabela. Para o navegador e para o dompdf esses espaços
     * não aparecem (o PDF sai idêntico), mas cada um vira um nó de texto que o dompdf mede, quebra e
     * reinicia a cada página; num relatório de 1000 linhas isso era ~10% do tempo de geração.
     */
    public static function compactar(string $html): string
    {
        return preg_replace('#\s*(</?(?:table|thead|tbody|tfoot|tr|td|th)\b[^>]*>)\s*#i', '$1', $html) ?? $html;
    }

    public static function aviso(int $total): ?string
    {
        if ($total <= self::LIMITE) {
            return null;
        }

        return 'Exibindo '.self::LIMITE.' de '.$total.' registros (exporte em CSV para a lista completa)';
    }
}
