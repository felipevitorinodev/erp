<?php

namespace App\Comandos;

/** Uma rotina que o assistente sabe fazer. */
interface Comando
{
    /** Identificador interno (vai na sessão junto com a prévia ou a pergunta). */
    public function chave(): string;

    /** Permissão do perfil exigida (CatalogoPermissoes). */
    public function permissao(): string;

    /** Nome curto mostrado nos botões ("Nova venda"). */
    public function titulo(): string;

    /** Frase que inicia a rotina sem nenhum dado ("nova venda"); o assistente pergunta o resto. */
    public function atalho(): string;

    /** @return list<string> frases de exemplo, do jeito que as pessoas falam */
    public function exemplos(): array;

    /** @return list<string> palavras (minúsculas, sem acento) que indicam esta rotina; também corrigem erros de digitação */
    public function palavras(): array;

    /** Regex do começo da frase ("cadastre cliente"), aplicada ao texto minúsculo e sem acento. */
    public function padrao(): string;

    /**
     * Reconhece o jeito natural de pedir ("a Maria comprou 2 coca no pix").
     * Devolve a frase reescrita no formato que preparar() entende, ou null.
     */
    public function reconhecerNatural(string $texto): ?string;

    /** Lê a frase e devolve prévia, pergunta (falta algo), erro ou consulta. Não grava nada. */
    public function preparar(string $argumento): Resposta;

    /** Grava os dados da prévia confirmada. */
    public function executar(array $dados): Resposta;

    /** Encaixa a resposta de uma pergunta na frase original, para preparar de novo. */
    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string;
}
