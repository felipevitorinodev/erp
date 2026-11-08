<?php

namespace App\Comandos;

use App\Models\Usuarios\User;

/**
 * Descobre qual rotina a frase pede, sem IA:
 * 1. tira a cortesia ("por favor", "quero", "preciso");
 * 2. procura o começo da frase de cada rotina ("cadastre cliente", "vendi");
 * 3. procura o jeito natural de falar ("a Maria comprou...", "quanto custa a coca");
 * 4. se nada bater, corrige a digitação das primeiras palavras e tenta de novo.
 */
final class Interpretador
{
    private const CORTESIA_INICIO = '/^(?:(?:por\s+favor|pfv|pf|favor|eu|entao|quero|queria|gostaria\s+de|preciso(?:\s+de)?|tenho\s+(?:que|de)|vou|vamos|pode|poderia|consegue|da\s+(?:pra|para)|tem\s+como|me\s+ajuda\s+a|ajuda\s+a|bora|oi|ola|ei|assistente|acabei\s+de|agora)[\s,!]+)+/u';

    private const CORTESIA_FIM = '/[\s,]+(?:por\s+favor|pfv|pf|obrigad[oa]|valeu)$/u';

    private ?Vocabulario $vocabulario = null;

    public function __construct(private readonly RegistroComandos $registro) {}

    /**
     * Qual rotina a frase pede e o resto dela (os dados).
     * "forte": a frase começa pelo nome da rotina. Com $tolerante = false (há uma pergunta esperando resposta)
     * só vale o começo exato, sem jeito natural nem correção de digitação: "Vanda" é resposta, não "venda".
     *
     * @return array{comando: Comando, argumento: string, forte: bool}|null
     */
    public function reconhecer(string $frase, bool $tolerante = true): ?array
    {
        $texto = self::semCortesia($frase);
        $tentativas = $tolerante ? [$texto, ...$this->vocabulario()->correcoes($texto)] : [$texto];

        foreach ($tentativas as $tentativa) {
            if ($achado = $this->procurar($tentativa, $tolerante)) {
                return $achado;
            }
        }

        return null;
    }

    /**
     * Rotinas mais prováveis pelas palavras da frase, para sugerir quando não deu para entender.
     *
     * @return list<Comando>
     */
    public function provaveis(string $frase, User $usuario, int $limite = 3): array
    {
        $palavras = array_unique($this->vocabulario()->palavrasDe($frase));
        $pontos = [];

        foreach ($this->registro->permitidos($usuario) as $comando) {
            $acertos = count(array_intersect($palavras, $comando->palavras()));
            if ($acertos > 0) {
                $pontos[] = ['comando' => $comando, 'acertos' => $acertos];
            }
        }

        usort($pontos, fn (array $a, array $b) => $b['acertos'] <=> $a['acertos']);

        return array_slice(array_column($pontos, 'comando'), 0, $limite);
    }

    /** "por favor, cadastra o cliente João, obrigado!" → "cadastra o cliente João". */
    public static function semCortesia(string $frase): string
    {
        $texto = rtrim(Leitor::limpar($frase), " \t?!.");
        $normal = Leitor::normalizar($texto);

        if (preg_match(self::CORTESIA_FIM, $normal, $fim)) {
            $texto = mb_substr($texto, 0, mb_strlen($normal) - mb_strlen($fim[0]));
            $normal = Leitor::normalizar($texto);
        }

        if (preg_match(self::CORTESIA_INICIO, $normal, $inicio) && mb_strlen($inicio[0]) < mb_strlen($normal)) {
            $texto = mb_substr($texto, mb_strlen($inicio[0]));
        }

        return trim($texto);
    }

    /** @return array{comando: Comando, argumento: string, forte: bool}|null */
    private function procurar(string $texto, bool $natural): ?array
    {
        $normal = Leitor::normalizar($texto);

        foreach ($this->registro->todos() as $comando) {
            if (preg_match($comando->padrao(), $normal, $inicio)) {
                return ['comando' => $comando, 'argumento' => trim(mb_substr($texto, mb_strlen($inicio[0]))), 'forte' => true];
            }
        }

        if (! $natural) {
            return null;
        }

        foreach ($this->registro->todos() as $comando) {
            $argumento = $comando->reconhecerNatural($texto);
            if ($argumento !== null) {
                return ['comando' => $comando, 'argumento' => trim($argumento), 'forte' => false];
            }
        }

        return null;
    }

    private function vocabulario(): Vocabulario
    {
        return $this->vocabulario ??= new Vocabulario(array_merge(
            ...array_map(fn (Comando $c) => $c->palavras(), $this->registro->todos())
        ));
    }
}
