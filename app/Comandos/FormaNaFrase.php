<?php

namespace App\Comandos;

use App\Models\FormasPagamento\FormaPagamento;

/** Tira da frase a forma de pagamento que o localizador reconheceu ("no pix", "pago no cartão"). */
final class FormaNaFrase
{
    /**
     * Com conector ("no cartão") sai toda menção, inclusive a de uma resposta anterior;
     * sem conector, só a primeira. Assim "cartão presente no cartão" mantém o produto.
     */
    public static function remover(Leitor $leitor, FormaPagamento $forma): void
    {
        $nome = Leitor::normalizar($forma->nome);
        $termos = array_values(array_filter(
            array_unique([$nome, ...(preg_split('/[\s\/-]+/', $nome) ?: [])]),
            fn (string $termo) => strlen($termo) >= 3,
        ));

        $removeu = false;
        foreach ($termos as $termo) {
            $comConector = '/(?:\b(?:pago|paga|pagamento)\s+(?:(?:no|na|em|via|com)\s+)?|\b(?:no|na|em|via|com)\s+)(?:cartao\s+(?:de\s+)?)?'.preg_quote($termo, '/').'\b/u';
            while ($leitor->extrair($comConector) !== null) {
                $removeu = true;
            }
        }

        if ($removeu) {
            return;
        }

        foreach ($termos as $termo) {
            if ($leitor->extrair('/\b(?:cartao\s+(?:de\s+)?)?'.preg_quote($termo, '/').'\b/u') !== null) {
                return;
            }
        }
    }
}
