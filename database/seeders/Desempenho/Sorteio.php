<?php

namespace Database\Seeders\Desempenho;

/**
 * Sorteios reproduzíveis (mt_rand com semente fixa) e documentos com dígito verificador válido.
 */
final class Sorteio
{
    /** @var array<string, true> */
    private static array $documentos = [];

    public static function iniciar(int $semente): void
    {
        mt_srand($semente);
        self::$documentos = [];
    }

    public static function int(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    public static function real(float $min, float $max): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }

    public static function chance(float $probabilidade): bool
    {
        return mt_rand() / mt_getrandmax() < $probabilidade;
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $lista
     * @return T
     */
    public static function item(array $lista): mixed
    {
        return $lista[mt_rand(0, count($lista) - 1)];
    }

    /** @param  array<int|string, int|float>  $pesos  chave => peso */
    public static function pesado(array $pesos): int|string
    {
        $alvo = self::real(0, array_sum($pesos));
        foreach ($pesos as $chave => $peso) {
            $alvo -= $peso;
            if ($alvo <= 0) {
                return $chave;
            }
        }

        return array_key_last($pesos);
    }

    /** Índice de 0 a n-1 que favorece o começo da lista (clientes antigos, produtos de maior giro). */
    public static function enviesado(int $n, float $expoente = 2.0): int
    {
        return min($n - 1, (int) floor($n * ((mt_rand() / mt_getrandmax()) ** $expoente)));
    }

    /** Quantidade de eventos num dia em torno da média (aproximação normal da distribuição de Poisson). */
    public static function quantidade(float $media): int
    {
        if ($media <= 0) {
            return 0;
        }
        $u1 = max(1e-9, mt_rand() / mt_getrandmax());
        $u2 = mt_rand() / mt_getrandmax();
        $normal = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);

        return max(0, (int) round($media + sqrt($media) * $normal));
    }

    public static function cpf(): string
    {
        do {
            $n = [];
            for ($i = 0; $i < 9; $i++) {
                $n[] = mt_rand(0, 9);
            }
            $n[] = self::digitoCpf($n, 10);
            $n[] = self::digitoCpf($n, 11);
            $numero = implode('', $n);
        } while (count(array_unique($n)) === 1 || isset(self::$documentos[$numero]));

        self::$documentos[$numero] = true;

        return vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($numero));
    }

    public static function cnpj(): string
    {
        do {
            $n = [];
            for ($i = 0; $i < 8; $i++) {
                $n[] = mt_rand(0, 9);
            }
            array_push($n, 0, 0, 0, mt_rand(1, 3));
            $n[] = self::digitoCnpj($n);
            $n[] = self::digitoCnpj($n);
            $numero = implode('', $n);
        } while (isset(self::$documentos[$numero]));

        self::$documentos[$numero] = true;

        return vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($numero));
    }

    /** EAN-13 com prefixo do Brasil (789/790) e dígito verificador. */
    public static function ean13(): string
    {
        do {
            $base = (mt_rand(0, 1) ? '789' : '790').str_pad((string) mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            $soma = 0;
            foreach (str_split($base) as $i => $d) {
                $soma += (int) $d * ($i % 2 === 0 ? 1 : 3);
            }
            $codigo = $base.((10 - $soma % 10) % 10);
        } while (isset(self::$documentos['ean'.$codigo]));

        self::$documentos['ean'.$codigo] = true;

        return $codigo;
    }

    /** Chave de acesso de NF-e de fornecedor (44 dígitos, com DV módulo 11). */
    public static function chaveNfe(string $uf, string $cnpj, string $anoMes, int $numero): string
    {
        $codigosUf = ['PB' => '25', 'PE' => '26', 'RN' => '24', 'SP' => '35', 'CE' => '23'];
        $base = ($codigosUf[$uf] ?? '25').$anoMes.preg_replace('/\D/', '', $cnpj).'55'.'001'
            .str_pad((string) $numero, 9, '0', STR_PAD_LEFT).'1'.str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $peso = 2;
        $soma = 0;
        for ($i = strlen($base) - 1; $i >= 0; $i--) {
            $soma += (int) $base[$i] * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }
        $resto = $soma % 11;

        return $base.($resto < 2 ? 0 : 11 - $resto);
    }

    public static function celular(string $ddd): string
    {
        return '('.$ddd.') 9'.mt_rand(8, 9).mt_rand(100, 999).'-'.str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public static function telefone(string $ddd): string
    {
        return '('.$ddd.') 3'.mt_rand(0, 9).mt_rand(10, 99).'-'.str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /** Endereço de e-mail em domínio reservado para exemplos (RFC 2606): nunca chega a uma pessoa real. */
    public static function email(string $nome): string
    {
        $partes = preg_split('/\s+/', mb_strtolower(trim($nome))) ?: ['cliente'];
        $usuario = preg_replace('/[^a-z0-9.]/', '', self::semAcento($partes[0].'.'.end($partes))) ?: 'cliente';

        return $usuario.mt_rand(1, 999).'@example.com';
    }

    public static function semAcento(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o',
            'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ç' => 'C',
        ]);
    }

    /** @param  list<int>  $n */
    private static function digitoCpf(array $n, int $peso): int
    {
        $soma = 0;
        foreach ($n as $i => $d) {
            $soma += $d * ($peso - $i);
        }
        $resto = ($soma * 10) % 11;

        return $resto === 10 ? 0 : $resto;
    }

    /** @param  list<int>  $n */
    private static function digitoCnpj(array $n): int
    {
        $pesos = count($n) === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        foreach ($n as $i => $d) {
            $soma += $d * $pesos[$i];
        }
        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}
