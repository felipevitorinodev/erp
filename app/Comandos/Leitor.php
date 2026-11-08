<?php

namespace App\Comandos;

use Carbon\CarbonImmutable;

/**
 * Lê as partes de uma frase de comando: valor, data, documento, contato e nomes com rótulo.
 *
 * Cada extração procura no texto normalizado (minúsculo e sem acento), devolve o trecho como foi
 * digitado e o tira da frase. O que sobra no fim vira nome ou descrição.
 */
final class Leitor
{
    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    /** Palavras de ligação que não fazem parte de nome nem de descrição nas pontas da frase. */
    private const CONECTIVOS = [
        'de', 'do', 'da', 'dos', 'das', 'para', 'pra', 'pro', 'com', 'e', 'o', 'a', 'os', 'as',
        'no', 'na', 'um', 'uma', 'chamado', 'chamada', 'nome', 'referente', 'ao', '-', ',', ';', ':',
    ];

    /** Palavras que encerram um nome com rótulo ("cliente Maria Souza vence amanhã"). */
    private const PARADAS = 'referente|ref|descricao|sobre|vence|vencendo|vencimento|valor|categoria|no|na|via|pago|paga|pagamento|forma|com|cpf|cnpj|telefone|tel|fone|celular|email|e-mail|defeito|problema|previsao|entrega|equipamento|aparelho|marca|modelo|estoque';

    private const NUMERO = '\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?|\d+(?:[.,]\d{1,2})?';

    /** "150 reais", "50 conto": a palavra da moeda sai junto com o número. */
    private const MOEDA_FALADA = '(?:\s*(?:reais|real|contos?|pilas?)\b)?';

    private const PREFIXO_DATA = '(?:(?:com\s+)?(?:vencimento|vencendo|vence|venc\.?)(?:\s+(?:em|no dia|dia|para))?\s+|para\s+o\s+dia\s+|no\s+dia\s+|para\s+|pra\s+|em\s+)?';

    /** Prefixo que deixa claro que vem uma data: só com ele "sexta" sozinha vira data ("referente a segunda parcela" não). */
    private const PREFIXO_FORTE = '(?:(?:com\s+)?(?:vencimento|vencendo|vence|venc\.?)(?:\s+(?:em|no dia|dia|para|na|no))?\s+|(?:para|pra|ate)\s+(?:(?:o|a)\s+)?)';

    private const DIA_DA_SEMANA = '(?:proxim[oa]\s+)?(?:segunda|terca|quarta|quinta|sexta|sabado|domingo)(?:-feira|\s+feira)?(?:\s+que\s+vem)?';

    private const DATA = 'hoje|amanha|depois\s+de\s+amanha|\d{1,2}[\/-]\d{1,2}(?:[\/-]\d{2,4})?|(?:daqui\s+a|daqui|em)\s+\d{1,3}\s+dias?|\d{1,3}\s+dias|dia\s+\d{1,2}'
        .'|(?:daqui\s+a|daqui|em)\s+(?:uma|1)\s+semana|(?:na\s+)?(?:semana\s+que\s+vem|proxima\s+semana)|(?:no\s+)?(?:mes\s+que\s+vem|proximo\s+mes)|(?:no\s+)?(?:fim|final)\s+do\s+mes'
        .'|(?:na\s+|no\s+)?proxim[oa]\s+(?:segunda|terca|quarta|quinta|sexta|sabado|domingo)(?:-feira|\s+feira)?'
        .'|(?:segunda|terca|quarta|quinta|sexta)(?:-feira|\s+feira)(?:\s+que\s+vem)?|(?:segunda|terca|quarta|quinta|sexta|sabado|domingo)\s+que\s+vem';

    private const DIAS_DA_SEMANA = ['domingo' => 0, 'segunda' => 1, 'terca' => 2, 'quarta' => 3, 'quinta' => 4, 'sexta' => 5, 'sabado' => 6];

    private string $original;

    private string $normal;

    public function __construct(string $texto)
    {
        $this->original = self::limpar($texto);
        $this->normal = self::normalizar($this->original);
    }

    /** Espaços repetidos viram um só. */
    public static function limpar(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    /** Minúsculo e sem acento, com exatamente um caractere para cada caractere do original. */
    public static function normalizar(string $texto): string
    {
        $saida = '';
        foreach (mb_str_split($texto) as $caractere) {
            $minusculo = mb_strtolower($caractere);
            $minusculo = self::ACENTOS[$minusculo] ?? $minusculo;
            $saida .= mb_strlen($minusculo) === 1 ? $minusculo : $caractere;
        }

        return $saida;
    }

    /** Número digitado do jeito brasileiro ("1.234,56", "150,5", "80") para float. */
    public static function numero(string $texto): float
    {
        $valor = str_replace(' ', '', $texto);
        if (str_contains($valor, ',')) {
            $valor = str_replace(['.', ','], ['', '.'], $valor);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $valor)) {
            $valor = str_replace('.', '', $valor);
        }

        return round((float) $valor, 2);
    }

    /**
     * Grupos da regex (aplicada ao texto minúsculo e sem acento) como foram digitados, sem alterar nada.
     *
     * @return array<int|string, string>|null
     */
    public static function grupos(string $regex, string $texto): ?array
    {
        return (new self($texto))->extrair($regex);
    }

    /**
     * Procura a regex no texto normalizado. Se achar, tira o trecho da frase e devolve os grupos
     * como foram digitados (índice 0 = trecho inteiro; grupos nomeados também vêm).
     *
     * @return array<int|string, string>|null
     */
    public function extrair(string $regex): ?array
    {
        if (! preg_match($regex, $this->normal, $achado, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL)) {
            return null;
        }

        $grupos = [];
        foreach ($achado as $chave => [$trecho, $byte]) {
            $grupos[$chave] = $trecho === null ? '' : $this->original($byte, $trecho);
        }

        [$trecho, $byte] = $achado[0];
        $inicio = mb_strlen(substr($this->normal, 0, $byte));
        $this->original = self::limpar(mb_substr($this->original, 0, $inicio).' '.mb_substr($this->original, $inicio + mb_strlen($trecho)));
        $this->normal = self::normalizar($this->original);

        return $grupos;
    }

    /** Valor em dinheiro: primeiro com "R$", depois com "valor", por último o número solto. */
    public function valor(): ?float
    {
        $tentativas = [
            '/(?:\bno valor de\s+|\bvalor(?: de)?\s*:?\s*)?r\$\s*('.self::NUMERO.')'.self::MOEDA_FALADA.'/u',
            '/\b(?:no valor de|valor(?: de)?)\s*:?\s*('.self::NUMERO.')(?![\d\/])'.self::MOEDA_FALADA.'/u',
            '/(?<![\w\/.,-])('.self::NUMERO.')'.self::MOEDA_FALADA.'(?![\w\/.,]|\s*(?:x\b|un\b|unid|dias\b|dia\b(?!\s*\d)|semanas?\b|%))/u',
        ];

        foreach ($tentativas as $regex) {
            $grupos = $this->extrair($regex);
            if ($grupos !== null) {
                return self::numero($grupos[1]);
            }
        }

        return null;
    }

    /**
     * Data como "hoje", "amanhã", "15/11", "dia 10", "em 30 dias", "sexta", "fim do mês" (com ou sem "vence").
     *
     * @throws EntradaInvalida quando a data não existe no calendário (com o trecho digitado)
     */
    public function data(CarbonImmutable $hoje): ?CarbonImmutable
    {
        $grupos = $this->extrair('/'.self::PREFIXO_FORTE.'\b('.self::DATA.'|'.self::DIA_DA_SEMANA.')\b/u')
            ?? $this->extrair('/'.self::PREFIXO_DATA.'\b('.self::DATA.')\b/u');

        if ($grupos === null) {
            return null;
        }

        try {
            return self::interpretarData(self::normalizar($grupos[1]), $hoje->startOfDay());
        } catch (EntradaInvalida $e) {
            throw new EntradaInvalida($e->getMessage(), $grupos[0]);
        }
    }

    /**
     * E-mail, CPF/CNPJ e telefone. A ordem evita confundir um telefone de 11 dígitos com CPF:
     * primeiro o que veio com rótulo, depois o que tem máscara, por último os dígitos soltos.
     *
     * @return array{email: ?string, documento: ?array{tipo: string, numero: string}, telefone: ?string, documento_digitado: ?string}
     *
     * @throws EntradaInvalida quando o documento ou o telefone têm dígitos de menos ou de mais (a mensagem não repete o número)
     */
    public function contatos(): array
    {
        $email = $this->extrair('/\b(?:e-?mail\s*:?\s*)?(?<email>[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,})/u');

        $documento = $this->extrair('/\b(?:cpf|cnpj|documento|doc)\s*:?\s*(?<num>[\d.\/-]{1,25})/u');
        $telefone = $this->extrair('/\b(?:telefone|tel|fone|celular|cel|whatsapp|whats|zap)\s*:?\s*(?<num>\+?[\d\s().-]{6,22}\d)/u');
        $documento ??= $this->extrair('/(?<!\d)(?<num>\d{3}\.\d{3}\.\d{3}-\d{2}|\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})(?!\d)/u');
        $telefone ??= $this->extrair('/(?<![\d])(?<num>\(\d{2}\)\s?\d{4,5}-?\d{4}|\d{2}\s\d{4,5}-?\d{4}|\d{4,5}-\d{4})(?![\d])/u');
        $documento ??= $this->extrair('/(?<![\d(])(?<num>\d{14}|\d{11})(?!\d)/u');

        try {
            $documentoLido = $documento ? self::documento($documento['num']) : null;
        } catch (EntradaInvalida $e) {
            throw new EntradaInvalida($e->getMessage(), $documento[0]);
        }

        try {
            $telefoneLido = $telefone ? self::telefone($telefone['num']) : null;
        } catch (EntradaInvalida $e) {
            throw new EntradaInvalida($e->getMessage(), $telefone[0]);
        }

        return [
            'email' => $email ? mb_strtolower($email['email']) : null,
            'documento' => $documentoLido,
            'telefone' => $telefoneLido,
            'documento_digitado' => $documento[0] ?? null,
        ];
    }

    /**
     * Nome depois de um rótulo ("cliente Maria Souza", "para Maria"), até a próxima palavra-chave.
     * Com $aceitar, o trecho só sai da frase se o nome for aceito (ex.: "para" só vale se for cliente).
     *
     * @param  (callable(string): bool)|null  $aceitar
     */
    public function rotulado(string $rotulos, ?callable $aceitar = null): ?string
    {
        $regex = '/\b(?:'.$rotulos.')\s+(?<nome>.+?)(?=\s+(?:'.self::PARADAS.')\b|\s*[,;]|$)/u';

        if ($aceitar !== null) {
            if (! preg_match($regex, $this->normal, $previa, PREG_OFFSET_CAPTURE)) {
                return null;
            }
            $nome = self::aparar($this->original($previa['nome'][1], $previa['nome'][0]));
            if ($nome === '' || ! $aceitar($nome)) {
                return null;
            }
        }

        $grupos = $this->extrair($regex);
        $nome = $grupos === null ? '' : self::aparar($grupos['nome']);

        return $nome !== '' ? $nome : null;
    }

    /** Descrição depois de "referente a", "ref", "descrição" ou "sobre". */
    public function descricao(): ?string
    {
        $grupos = $this->extrair('/\b(?:referente\s+(?:a|ao|aos|as)|referente|ref\.?|descricao|desc\.?|sobre)\s*:?\s+(?<texto>.+)$/u');
        $texto = $grupos === null ? '' : self::aparar($grupos['texto']);

        return $texto !== '' ? $texto : null;
    }

    /** O que sobrou da frase, sem palavras de ligação nas pontas. */
    public function resto(): string
    {
        return self::aparar($this->original);
    }

    public static function aparar(string $texto): string
    {
        $palavras = preg_split('/\s+/u', trim($texto, " \t,;:-")) ?: [];
        while ($palavras !== [] && in_array(self::normalizar((string) $palavras[0]), self::CONECTIVOS, true)) {
            array_shift($palavras);
        }
        while ($palavras !== [] && in_array(self::normalizar((string) end($palavras)), self::CONECTIVOS, true)) {
            array_pop($palavras);
        }

        return trim(implode(' ', $palavras), " \t,;:-");
    }

    private function original(int $byte, string $trechoNormal): string
    {
        return mb_substr($this->original, mb_strlen(substr($this->normal, 0, $byte)), mb_strlen($trechoNormal));
    }

    /** @return array{tipo: string, numero: string} com a mesma máscara do formulário de cliente */
    private static function documento(string $digitado): array
    {
        $digitos = (string) preg_replace('/\D/', '', $digitado);

        return match (strlen($digitos)) {
            11 => ['tipo' => 'cpf', 'numero' => vsprintf('%s.%s.%s-%s', str_split($digitos, 3))],
            14 => ['tipo' => 'cnpj', 'numero' => sprintf('%s.%s.%s/%s-%s', substr($digitos, 0, 2), substr($digitos, 2, 3), substr($digitos, 5, 3), substr($digitos, 8, 4), substr($digitos, 12))],
            default => throw new EntradaInvalida('O CPF/CNPJ informado tem '.strlen($digitos).' dígitos. CPF tem 11 e CNPJ tem 14.'),
        };
    }

    private static function telefone(string $digitado): string
    {
        $digitos = (string) preg_replace('/\D/', '', $digitado);
        if (strlen($digitos) < 8 || strlen($digitos) > 13) {
            throw new EntradaInvalida('O telefone parece incompleto. Informe com DDD, por exemplo (11) 98765-4321.');
        }

        return trim($digitado);
    }

    private static function interpretarData(string $texto, CarbonImmutable $hoje): CarbonImmutable
    {
        $texto = self::limpar($texto);

        return match (true) {
            $texto === 'hoje' => $hoje,
            $texto === 'amanha' => $hoje->addDay(),
            $texto === 'depois de amanha' => $hoje->addDays(2),
            (bool) preg_match('/(\d{1,3}) dias?$/', $texto, $m) => $hoje->addDays((int) $m[1]),
            (bool) preg_match('/^dia (\d{1,2})$/', $texto, $m) => self::proximoDia((int) $m[1], $hoje),
            (bool) preg_match('/\b(segunda|terca|quarta|quinta|sexta|sabado|domingo)\b/', $texto, $m) => $hoje->next(self::DIAS_DA_SEMANA[$m[1]])->startOfDay(),
            (bool) preg_match('/(?:fim|final) do mes/', $texto) => $hoje->endOfMonth()->startOfDay(),
            (bool) preg_match('/semana/', $texto) => $hoje->addWeek(),
            (bool) preg_match('/mes que vem|proximo mes/', $texto) => $hoje->addMonthNoOverflow(),
            default => self::dataDigitada($texto, $hoje),
        };
    }

    private static function dataDigitada(string $texto, CarbonImmutable $hoje): CarbonImmutable
    {
        preg_match('/^(\d{1,2})[\/-](\d{1,2})(?:[\/-](\d{2,4}))?$/', $texto, $m);
        $dia = (int) $m[1];
        $mes = (int) $m[2];
        $anoInformado = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null;
        if ($anoInformado !== null && $anoInformado < 100) {
            $anoInformado += 2000;
        }
        $ano = $anoInformado ?? $hoje->year;

        if (! checkdate($mes, $dia, $ano)) {
            throw new EntradaInvalida('A data '.$texto.' não existe no calendário.');
        }

        $data = CarbonImmutable::create($ano, $mes, $dia)->startOfDay();

        // Sem ano e já passou: é do ano seguinte ("10/01" digitado em dezembro).
        return $anoInformado === null && $data->lt($hoje) ? $data->addYear() : $data;
    }

    /** "dia 10": a próxima data com esse dia, neste mês ou nos seguintes. */
    private static function proximoDia(int $dia, CarbonImmutable $hoje): CarbonImmutable
    {
        if ($dia < 1 || $dia > 31) {
            throw new EntradaInvalida('Não existe dia '.$dia.' no mês.');
        }

        for ($meses = 0; $meses < 3; $meses++) {
            $mes = $hoje->startOfMonth()->addMonths($meses);
            if ($dia <= $mes->daysInMonth && $mes->day($dia)->gte($hoje)) {
                return $mes->day($dia);
            }
        }

        return $hoje->startOfMonth()->addMonths(2)->endOfMonth()->startOfDay();
    }
}
