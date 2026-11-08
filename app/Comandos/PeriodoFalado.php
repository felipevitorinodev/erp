<?php

namespace App\Comandos;

use Carbon\CarbonImmutable;

/**
 * Período dito em voz alta: "ontem", "esta semana", "01/11 a 05/11".
 * Dia e mês sem ano ficam no ano corrente (a resposta mostra o ano). "sexta" é a próxima.
 */
final class PeriodoFalado
{
    private function __construct(
        public readonly CarbonImmutable $inicio,
        public readonly CarbonImmutable $fim,
        public readonly ?string $apelido,
    ) {}

    /** @return list<string> */
    public static function sugestoes(): array
    {
        return ['Hoje', 'Ontem', 'Amanhã', 'Esta semana', 'Este mês'];
    }

    /** "Ontem (06/11/2026)" ou "01/11/2026 a 05/11/2026". */
    public function rotulo(): string
    {
        $datas = $this->inicio->equalTo($this->fim)
            ? $this->inicio->format('d/m/Y')
            : $this->inicio->format('d/m/Y').' a '.$this->fim->format('d/m/Y');

        return $this->apelido !== null ? $this->apelido.' ('.$datas.')' : $datas;
    }

    /** @throws EntradaInvalida */
    public static function interpretar(string $texto, CarbonImmutable $hoje): ?self
    {
        $hoje = $hoje->startOfDay();
        $limpo = self::limpar(Leitor::normalizar($texto));

        if ($limpo === '') {
            return null;
        }

        $nomeado = self::nomeado($limpo, $hoje);
        if ($nomeado !== null) {
            return $nomeado;
        }

        if (preg_match('/^(.+?)\s+(?:a|ate|e)\s+(.+)$/u', $limpo, $lados)) {
            $inicio = self::lado($lados[1], $hoje);
            $fim = self::lado($lados[2], $hoje);

            if ($inicio !== null && $fim !== null) {
                if ($fim->lt($inicio)) {
                    throw new EntradaInvalida('A data final é anterior à inicial.', trim($texto));
                }

                return new self($inicio, $fim, null);
            }

            if ($inicio === null && self::pareceData($lados[1])) {
                self::dataUnica($lados[1], $hoje);
            }
            if ($fim === null && self::pareceData($lados[2])) {
                self::dataUnica($lados[2], $hoje);
            }
        }

        $unica = self::dataUnica($limpo, $hoje);

        return $unica === null ? null : new self($unica, $unica, null);
    }

    /**
     * Uma data no meio da frase ("entrega sexta", "para amanhã", "15/12"). Tira o trecho.
     * Com $aceitaSolta falso, só vale data com rótulo ("entrega sexta"); "sexta" solta fica na frase.
     *
     * @throws EntradaInvalida
     */
    public static function extrairDaFrase(Leitor $leitor, CarbonImmutable $hoje, bool $aceitaSolta = true): ?CarbonImmutable
    {
        $rotulada = self::extrairRotulo($leitor, 'previsao|entrega', $hoje);

        return $rotulada ?? ($aceitaSolta ? $leitor->data($hoje->startOfDay()) : null);
    }

    /**
     * "abertura ontem", "data 10/11", "entrega sexta". Sem o rótulo, não mexe na frase.
     *
     * @throws EntradaInvalida
     */
    public static function extrairRotulo(Leitor $leitor, string $rotulos, CarbonImmutable $hoje): ?CarbonImmutable
    {
        $quando = 'hoje|ontem|anteontem|amanha|depois\s+de\s+amanha|(?:proxim[oa]\s+)?(?:segunda|terca|quarta|quinta|sexta|sabado|domingo)(?:-feira|\s+feira)?'
            .'|\d{1,2}[\/-]\d{1,2}(?:[\/-]\d{2,4})?|dia\s+\d{1,2}|(?:em\s+)?\d{1,3}\s+dias?|(?:no\s+)?(?:fim|final)\s+do\s+mes';

        $grupos = $leitor->extrair('/\b(?:'.$rotulos.')(?:\s+(?:para|pra|em|dia))?\s+(?<quando>'.$quando.')\b/u');
        if ($grupos === null) {
            return null;
        }

        $data = self::dataUnica($grupos['quando'], $hoje->startOfDay());
        if ($data === null) {
            throw new EntradaInvalida('Não entendi a data.', $grupos[0]);
        }

        return $data;
    }

    /** @throws EntradaInvalida */
    public static function dataUnica(string $texto, CarbonImmutable $hoje): ?CarbonImmutable
    {
        $hoje = $hoje->startOfDay();
        $texto = Leitor::limpar($texto);
        $normal = Leitor::normalizar($texto);

        if ($normal === '') {
            return null;
        }

        $nomeada = match ($normal) {
            'hoje' => $hoje,
            'ontem' => $hoje->subDay(),
            'anteontem' => $hoje->subDays(2),
            'amanha' => $hoje->addDay(),
            'depois de amanha' => $hoje->addDays(2),
            default => null,
        };

        if ($nomeada !== null) {
            return $nomeada;
        }

        if (preg_match('/^\d{1,2}[\/-]\d{1,2}(?:[\/-]\d{2,4})?$/', $normal)) {
            return self::dataCalendario($normal, $hoje);
        }

        return (new Leitor('para '.$texto))->data($hoje);
    }

    private static function nomeado(string $limpo, CarbonImmutable $hoje): ?self
    {
        $semana = fn (int $deslocamento, string $apelido) => self::semana($hoje, $deslocamento, $apelido);
        $mes = fn (int $deslocamento, string $apelido) => self::mes($hoje, $deslocamento, $apelido);

        return match ($limpo) {
            'hoje' => new self($hoje, $hoje, 'Hoje'),
            'ontem' => new self($hoje->subDay(), $hoje->subDay(), 'Ontem'),
            'anteontem' => new self($hoje->subDays(2), $hoje->subDays(2), 'Anteontem'),
            'amanha' => new self($hoje->addDay(), $hoje->addDay(), 'Amanhã'),
            'depois de amanha' => new self($hoje->addDays(2), $hoje->addDays(2), 'Depois de amanhã'),
            'semana', 'esta semana', 'nesta semana', 'essa semana' => $semana(0, 'Esta semana'),
            'semana passada', 'ultima semana' => $semana(-1, 'Semana passada'),
            'semana que vem', 'proxima semana' => $semana(1, 'Semana que vem'),
            'mes', 'este mes', 'neste mes', 'esse mes' => $mes(0, 'Este mês'),
            'mes passado', 'ultimo mes' => $mes(-1, 'Mês passado'),
            'mes que vem', 'proximo mes' => $mes(1, 'Mês que vem'),
            default => null,
        };
    }

    private static function semana(CarbonImmutable $hoje, int $deslocamento, string $apelido): self
    {
        $inicio = $hoje->startOfWeek(CarbonImmutable::MONDAY)->addWeeks($deslocamento)->startOfDay();

        return new self($inicio, $inicio->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay(), $apelido);
    }

    private static function mes(CarbonImmutable $hoje, int $deslocamento, string $apelido): self
    {
        $inicio = $hoje->startOfMonth()->addMonthsNoOverflow($deslocamento)->startOfMonth()->startOfDay();

        return new self($inicio, $inicio->endOfMonth()->startOfDay(), $apelido);
    }

    /** @throws EntradaInvalida */
    private static function lado(string $texto, CarbonImmutable $hoje): ?CarbonImmutable
    {
        try {
            return self::dataUnica($texto, $hoje);
        } catch (EntradaInvalida) {
            return null;
        }
    }

    private static function pareceData(string $texto): bool
    {
        return (bool) preg_match('/\d{1,2}[\/-]\d{1,2}|\b(?:hoje|ontem|amanha|segunda|terca|quarta|quinta|sexta|sabado|domingo|dia)\b/u', Leitor::normalizar($texto));
    }

    /** @throws EntradaInvalida */
    private static function dataCalendario(string $texto, CarbonImmutable $hoje): CarbonImmutable
    {
        preg_match('/^(\d{1,2})[\/-](\d{1,2})(?:[\/-](\d{2,4}))?$/', $texto, $m);
        $dia = (int) $m[1];
        $mes = (int) $m[2];
        $ano = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : $hoje->year;
        if ($ano < 100) {
            $ano += 2000;
        }

        if (! checkdate($mes, $dia, $ano)) {
            throw new EntradaInvalida('A data '.$texto.' não existe no calendário.', $texto);
        }

        return CarbonImmutable::create($ano, $mes, $dia)->startOfDay();
    }

    private static function limpar(string $normal): string
    {
        $prefixo = '/^(?:que\s+vencem|que\s+vence|a\s+vencer|com\s+vencimento|vencimento|vencendo|vencem|vence|do\s+dia|no\s+dia|entre|durante|nesta|neste|nessa|nesse|desta|deste|dessa|desse|esta|este|essa|esse|para|pra|de|do|da|em|no|na|o|a)\s+/u';

        do {
            $anterior = $normal;
            $normal = preg_replace($prefixo, '', $normal) ?? $normal;
        } while ($normal !== $anterior && $normal !== '');

        return trim($normal);
    }
}
