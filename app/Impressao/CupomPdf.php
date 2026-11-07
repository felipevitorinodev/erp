<?php

namespace App\Impressao;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class CupomPdf
{
    private const LARGURA = 226.77;

    public static function stream(array $dados, string $arquivo): Response
    {
        return Pdf::loadView('impressao.cupom', [
            'dados' => $dados,
            'pdf' => true,
        ])->setPaper([0, 0, self::LARGURA, self::altura($dados)])->stream($arquivo);
    }

    /**
     * Mede a altura real do cupom numa folha alta e devolve só o
     * necessário para uma página, sem sobra nem quebra.
     *
     * @param  array<string, mixed>  $dados
     */
    private static function altura(array $dados): float
    {
        $pdf = Pdf::loadView('impressao.cupom', [
            'dados' => $dados,
            'pdf' => true,
        ]);
        $pdf->setPaper([0, 0, self::LARGURA, 5000]);

        $altura = 0.0;
        $pdf->getDomPDF()->setCallbacks([
            [
                'event' => 'end_frame',
                'f' => function ($frame) use (&$altura): void {
                    $node = $frame->get_node();
                    if ($node === null || strtolower($node->nodeName) !== 'body') {
                        return;
                    }

                    $altura = (float) $frame->get_margin_height();
                },
            ],
        ]);
        $pdf->render();

        if ($altura < 1) {
            $altura = 160;
        }

        return round($altura + 1, 2);
    }
}
