<?php

namespace App\Comandos;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * O que o assistente espera do usuário: uma confirmação (prévia) ou a resposta a uma pergunta.
 * Fica na sessão e vale por pouco tempo. Da prévia, o navegador só recebe o token.
 */
final class Conversa
{
    private const CHAVE = 'assistente.conversa';

    /** Fora de CHAVE: a sessão usa ponto como caminho, e esquecer() a conversa atual apagaria a pilha junto. */
    private const PILHA = 'assistente.pausadas';

    private const VALIDADE_SEGUNDOS = 600;

    private const MAX_FLUXOS_PAUSADOS = 5;

    public function __construct(private readonly Session $sessao) {}

    /** Guarda a prévia (substitui o que estava pendente) e devolve o token de confirmação. */
    public function guardarPrevia(string $comando, array $dados): string
    {
        $token = Str::random(32);
        $this->guardar(['tipo' => 'previa', 'comando' => $comando, 'dados' => $dados, 'token' => $token]);

        return $token;
    }

    /** Guarda a pergunta: a próxima mensagem completa o pedido original. */
    public function guardarPergunta(string $comando, string $argumento, string $campo, ?string $termo): void
    {
        $this->guardar(['tipo' => 'pergunta', 'comando' => $comando, 'argumento' => $argumento, 'campo' => $campo, 'termo' => $termo]);
    }

    public function temPrevia(): bool
    {
        return ($this->atual()['tipo'] ?? null) === 'previa';
    }

    /** @return array{comando: string, argumento: string, campo: string, termo: ?string}|null */
    public function pergunta(): ?array
    {
        $estado = $this->atual();
        if (($estado['tipo'] ?? null) !== 'pergunta') {
            return null;
        }

        return [
            'comando' => (string) $estado['comando'],
            'argumento' => (string) $estado['argumento'],
            'campo' => (string) $estado['campo'],
            'termo' => $estado['termo'] ?? null,
        ];
    }

    /**
     * Tira a prévia da sessão (uso único). Sem token, vale a prévia atual (resposta "sim").
     *
     * @return array{comando: string, dados: array}|null
     */
    public function retirarPrevia(?string $token = null): ?array
    {
        $estado = $this->atual();
        if (($estado['tipo'] ?? null) !== 'previa' || ($token !== null && ! hash_equals((string) $estado['token'], $token))) {
            return null;
        }

        $this->esquecer();

        return ['comando' => (string) $estado['comando'], 'dados' => (array) $estado['dados']];
    }

    public function esquecer(): void
    {
        $this->sessao->forget(self::CHAVE);
    }

    /** Pausa uma pergunta ativa para permitir uma tarefa auxiliar. */
    public function pausar(): bool
    {
        $estado = $this->atual();
        if (($estado['tipo'] ?? null) !== 'pergunta') {
            return false;
        }

        $pilha = $this->pausadas();
        if (count($pilha) >= self::MAX_FLUXOS_PAUSADOS) {
            return false;
        }

        $pilha[] = $estado;
        $this->sessao->put(self::PILHA, $pilha);
        $this->esquecer();

        return true;
    }

    public function temPausada(): bool
    {
        return $this->pausadas() !== [];
    }

    /**
     * Tira da pilha a tarefa mais recente que ainda não expirou. Quem retoma prepara a rotina de novo
     * e guarda a pergunta ou a prévia que ela devolver; se a rotina já terminar, nada fica pendente.
     */
    public function retomar(): ?array
    {
        $pilha = $this->pausadas();
        $estado = array_pop($pilha);
        $this->sessao->put(self::PILHA, $pilha);

        return $estado;
    }

    private function guardar(array $estado): void
    {
        $this->sessao->put(self::CHAVE, $estado + ['expira' => now()->timestamp + self::VALIDADE_SEGUNDOS]);
    }

    /** @return list<array<string, mixed>> */
    private function pausadas(): array
    {
        $estados = $this->sessao->get(self::PILHA, []);
        if (! is_array($estados)) {
            return [];
        }

        $agora = now()->timestamp;
        $validas = array_values(array_filter(
            $estados,
            fn ($estado) => is_array($estado) && ($estado['expira'] ?? 0) > $agora,
        ));

        if (count($validas) !== count($estados)) {
            $this->sessao->put(self::PILHA, $validas);
        }

        return $validas;
    }

    private function atual(): ?array
    {
        $estado = $this->sessao->get(self::CHAVE);
        if (! is_array($estado)) {
            return null;
        }

        if (($estado['expira'] ?? 0) <= now()->timestamp) {
            $this->esquecer();

            return null;
        }

        return $estado;
    }
}
