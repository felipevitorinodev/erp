<?php

namespace App\Comandos;

/**
 * Tolerância a erro de digitação, sem IA: compara palavras pela distância de edição
 * ("clinete" está a 1 troca de "cliente") e nomes pela semelhança das partes.
 */
final class Vocabulario
{
    private const TAMANHO_MINIMO = 4;

    /** Só as primeiras palavras da frase são corrigidas: é onde fica o pedido; depois vêm nomes. */
    private const PALAVRAS_CORRIGIDAS = 3;

    /** @var list<string> */
    private array $palavras;

    /** @param iterable<string> $palavras palavras conhecidas (as das rotinas) */
    public function __construct(iterable $palavras)
    {
        $lista = [];
        foreach ($palavras as $palavra) {
            $normal = Leitor::normalizar($palavra);
            if (strlen($normal) >= self::TAMANHO_MINIMO) {
                $lista[$normal] = true;
            }
        }
        $this->palavras = array_keys($lista);
    }

    /**
     * A frase com as primeiras palavras corrigidas, uma de cada vez
     * ("cadastar clinete Vanda" → "cadastrar clinete Vanda" → "cadastrar cliente Vanda").
     * Quem chama para na primeira versão que fizer sentido, sem mexer no nome.
     *
     * @return list<string>
     */
    public function correcoes(string $texto): array
    {
        $palavras = explode(' ', Leitor::limpar($texto));
        $versoes = [];

        foreach (array_slice(array_keys($palavras), 0, self::PALAVRAS_CORRIGIDAS) as $indice) {
            $certa = $this->corrigir($palavras[$indice]);
            if ($certa !== null) {
                $palavras[$indice] = $certa;
                $versoes[] = implode(' ', $palavras);
            }
        }

        return $versoes;
    }

    /**
     * Palavras da frase (minúsculas, sem acento), com a digitação corrigida.
     *
     * @return list<string>
     */
    public function palavrasDe(string $texto): array
    {
        $saida = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', Leitor::normalizar($texto)) ?: [] as $palavra) {
            if ($palavra !== '') {
                $saida[] = $this->corrigir($palavra) ?? $palavra;
            }
        }

        return $saida;
    }

    /** A palavra conhecida que a pessoa quis digitar, ou null (já está certa ou não parece nenhuma). */
    public function corrigir(string $palavra): ?string
    {
        $normal = Leitor::normalizar(trim($palavra, ',.;:!?'));
        $tamanho = strlen($normal);

        if ($tamanho < self::TAMANHO_MINIMO || preg_match('/[^a-z]/', $normal) || in_array($normal, $this->palavras, true)) {
            return null;
        }

        $tolerancia = $tamanho >= 8 ? 2 : 1;
        $melhor = null;
        $menor = $tolerancia + 1;

        foreach ($this->palavras as $certa) {
            if (abs(strlen($certa) - $tamanho) > $tolerancia) {
                continue;
            }
            $distancia = self::distancia($normal, $certa);
            if ($distancia < $menor) {
                $melhor = $certa;
                $menor = $distancia;
            }
        }

        return $melhor;
    }

    /** Distância de edição em que trocar duas letras vizinhas conta como um erro só ("clinete" → "cliente" = 1). */
    public static function distancia(string $a, string $b): int
    {
        $tamanhoA = strlen($a);
        $tamanhoB = strlen($b);
        $d = [];

        for ($i = 0; $i <= $tamanhoA; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $tamanhoB; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $tamanhoA; $i++) {
            for ($j = 1; $j <= $tamanhoB; $j++) {
                $custo = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $custo);

                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $d[$tamanhoA][$tamanhoB];
    }

    /**
     * Semelhança de 0 a 1 entre o que foi digitado e um nome cadastrado.
     * Vale a melhor de duas medidas: palavra a palavra ("Mria Souza" × "Maria Souza")
     * e o texto todo sem espaços ("cocacola" × "Coca-Cola Lata").
     */
    public static function semelhanca(string $digitado, string $nome): float
    {
        $partesDigitadas = self::partes($digitado);
        $partesDoNome = self::partes($nome);

        if ($partesDigitadas === [] || $partesDoNome === []) {
            return 0.0;
        }

        $soma = 0.0;
        foreach ($partesDigitadas as $parte) {
            $melhor = 0.0;
            foreach ($partesDoNome as $doNome) {
                $melhor = max($melhor, 1 - levenshtein($parte, $doNome) / max(strlen($parte), strlen($doNome)));
            }
            $soma += $melhor;
        }

        similar_text(implode('', $partesDigitadas), implode('', $partesDoNome), $percentual);

        return max($soma / count($partesDigitadas), $percentual / 100);
    }

    /** @return list<string> */
    private static function partes(string $texto): array
    {
        return array_values(array_filter(preg_split('/[^a-z0-9]+/', Leitor::normalizar($texto)) ?: []));
    }
}
