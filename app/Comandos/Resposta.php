<?php

namespace App\Comandos;

/**
 * Uma mensagem do assistente: uma frase principal e, se precisar, poucos detalhes e botões de resposta.
 * Os dados para gravar (dados()) nunca vão para o navegador: ficam na sessão até a confirmação.
 */
final class Resposta
{
    private ?string $token = null;

    /** "confirmada", "cancelada" ou "encerrada": a tela fecha a prévia aberta. */
    private ?string $previaEncerrada = null;

    /**
     * @param  list<string>  $detalhes  linhas curtas abaixo da frase
     * @param  list<array{descricao: string, valor: string}>  $itens  itens de venda ou de consulta
     * @param  list<string>  $avisos
     * @param  list<array{rotulo: string, enviar: string}>  $opcoes  botões que respondem pelo usuário
     * @param  array{rotulo: string, url: string}|null  $link
     * @param  array{campo: string, termo: ?string}|null  $pergunta  o que a próxima mensagem completa
     */
    private function __construct(
        public readonly string $tipo,
        public readonly string $texto,
        public readonly array $detalhes = [],
        public readonly array $itens = [],
        public readonly array $avisos = [],
        public readonly array $opcoes = [],
        public readonly ?array $link = null,
        private readonly ?string $comando = null,
        private readonly array $dados = [],
        private readonly ?array $registro = null,
        private readonly ?array $pergunta = null,
    ) {}

    /** Prévia que só grava depois do "Confirmar" (ou de um "sim"). */
    public static function confirmacao(string $comando, string $texto, array $dados, array $detalhes = [], array $itens = [], array $avisos = []): self
    {
        return new self('confirmacao', $texto, $detalhes, $itens, $avisos, comando: $comando, dados: $dados);
    }

    /** @param array{entidade: string, id: int}|null $registro o que foi gravado (vai para a auditoria) */
    public static function sucesso(string $texto, ?array $link = null, array $detalhes = [], array $avisos = [], ?array $registro = null): self
    {
        return new self('sucesso', $texto, $detalhes, avisos: $avisos, link: $link, registro: $registro);
    }

    /** @param list<string|array{rotulo: string, enviar: string}> $opcoes */
    public static function erro(string $texto, array $opcoes = []): self
    {
        return new self('erro', $texto, opcoes: self::normalizarOpcoes($opcoes));
    }

    /**
     * Falta uma informação: a próxima mensagem do usuário é a resposta.
     *
     * @param  string  $campo  o que está sendo perguntado (cada rotina sabe encaixar a resposta)
     * @param  list<string|array{rotulo: string, enviar: string}>  $opcoes  respostas prontas
     * @param  string|null  $termo  trecho da frase que a resposta substitui (busca com vários resultados, dado errado)
     */
    public static function pergunta(string $texto, string $campo, array $opcoes = [], ?string $termo = null): self
    {
        return new self('pergunta', $texto, opcoes: self::normalizarOpcoes($opcoes), pergunta: ['campo' => $campo, 'termo' => $termo]);
    }

    /** @param list<string|array{rotulo: string, enviar: string}> $opcoes */
    public static function info(string $texto, array $detalhes = [], array $itens = [], array $opcoes = [], ?array $link = null): self
    {
        return new self('info', $texto, $detalhes, $itens, opcoes: self::normalizarOpcoes($opcoes), link: $link);
    }

    /** Botão cujo texto é diferente do que ele envia ("Nova venda" envia "nova venda"; um cliente envia "#15"). */
    public static function opcao(string $rotulo, string $enviar): array
    {
        return ['rotulo' => $rotulo, 'enviar' => $enviar];
    }

    public function precisaConfirmar(): bool
    {
        return $this->tipo === 'confirmacao';
    }

    public function comando(): ?string
    {
        return $this->comando;
    }

    public function dados(): array
    {
        return $this->dados;
    }

    /** @return array{entidade: string, id: int}|null */
    public function registro(): ?array
    {
        return $this->registro;
    }

    /** @return array{campo: string, termo: ?string}|null o que a próxima mensagem completa */
    public function perguntaPendente(): ?array
    {
        return $this->pergunta;
    }

    public function comToken(string $token): self
    {
        $copia = clone $this;
        $copia->token = $token;

        return $copia;
    }

    public function encerrandoPrevia(string $como): self
    {
        $copia = clone $this;
        $copia->previaEncerrada = $como;

        return $copia;
    }

    /** Mesma resposta com algo antes da frase ("Não entendi. Qual o valor?"). */
    public function comTextoAntes(string $prefixo): self
    {
        return new self(
            $this->tipo, $prefixo.$this->texto, $this->detalhes, $this->itens, $this->avisos, $this->opcoes,
            $this->link, $this->comando, $this->dados, $this->registro, $this->pergunta,
        );
    }

    /** Formato enviado ao navegador (sem os dados internos). */
    public function paraJson(): array
    {
        return array_filter([
            'tipo' => $this->tipo,
            'texto' => $this->texto,
            'detalhes' => $this->detalhes,
            'itens' => $this->itens,
            'avisos' => $this->avisos,
            'opcoes' => $this->opcoes,
            'link' => $this->link,
            'token' => $this->token,
            'previa' => $this->previaEncerrada,
        ], fn ($valor) => $valor !== null && $valor !== []);
    }

    /**
     * @param  list<string|array{rotulo: string, enviar: string}>  $opcoes
     * @return list<array{rotulo: string, enviar: string}>
     */
    private static function normalizarOpcoes(array $opcoes): array
    {
        return array_values(array_map(
            fn ($opcao) => is_array($opcao) ? $opcao : ['rotulo' => (string) $opcao, 'enviar' => (string) $opcao],
            $opcoes,
        ));
    }
}
