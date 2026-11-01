<?php

namespace App\Relatorios;

class CurvaAbc
{
    /**
     * Classe A até 80% do valor, B até 95% e C no restante.
     * O item que atravessa o corte permanece na classe em que entrou.
     *
     * @param  list<array<string, mixed>>  $linhas
     * @return list<array<string, mixed>>
     */
    public static function classificar(array $linhas, string $campo = 'valor'): array
    {
        usort($linhas, function (array $a, array $b) use ($campo): int {
            $valor = ((float) $b[$campo]) <=> ((float) $a[$campo]);

            if ($valor !== 0) {
                return $valor;
            }

            return ((int) ($a['cliente_id'] ?? PHP_INT_MAX)) <=> ((int) ($b['cliente_id'] ?? PHP_INT_MAX));
        });

        $totalCentavos = 0;
        foreach ($linhas as $linha) {
            $totalCentavos += self::centavos($linha[$campo] ?? 0);
        }

        $acumulado = 0;
        $classe = 'A';

        foreach ($linhas as $indice => $linha) {
            $centavos = self::centavos($linha[$campo] ?? 0);
            $acumulado += $centavos;
            $linha['percentual'] = $totalCentavos > 0 ? round($centavos * 100 / $totalCentavos, 1) : 0.0;
            $linha['acumulado'] = $totalCentavos > 0 ? round($acumulado * 100 / $totalCentavos, 1) : 0.0;
            $linha['classe'] = $totalCentavos > 0 ? $classe : 'C';
            $linhas[$indice] = $linha;

            if ($totalCentavos <= 0) {
                continue;
            }

            if ($classe === 'A' && $acumulado * 100 >= $totalCentavos * 80) {
                $classe = 'B';
            }

            if ($classe === 'B' && $acumulado * 100 >= $totalCentavos * 95) {
                $classe = 'C';
            }
        }

        return $linhas;
    }

    private static function centavos(mixed $valor): int
    {
        return (int) round(((float) $valor) * 100);
    }
}
