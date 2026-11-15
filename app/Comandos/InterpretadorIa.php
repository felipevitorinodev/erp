<?php

namespace App\Comandos;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reescreve uma frase solta no formato de uma rotina já mapeada.
 * Não grava, não inventa número nem e-mail, e só devolve uma chave que o perfil pode usar.
 * Fora do ar ou resposta inútil: devolve null e o assistente segue só com as regras.
 */
final class InterpretadorIa
{
    private const LOCAIS = ['localhost', '127.0.0.1', '::1', 'host.docker.internal', '0.0.0.0'];

    /**
     * @param  list<Comando>  $comandos  só as rotinas que o perfil pode usar
     * @return array{chave: string, frase: string}|null
     */
    public function reescrever(string $frase, array $comandos): ?array
    {
        if (! $this->ativa() || $comandos === [] || mb_strlen($frase) < 4) {
            return null;
        }

        $url = $this->endereco();
        $modelo = trim((string) config('assistente.ia.modelo'));
        if ($url === null || $modelo === '') {
            return null;
        }

        $porChave = [];
        foreach ($comandos as $comando) {
            $porChave[$comando->chave()] = true;
        }

        $corpo = $this->corpo($modelo, $this->instrucoes(array_keys($porChave), $comandos), $frase);

        try {
            $resposta = $this->consultar($url, $corpo);
        } catch (ConnectionException $e) {
            Log::warning('Assistente: a IA não respondeu.', ['erro' => $e->getMessage()]);

            return null;
        }

        if ($resposta->status() === 400 && isset($corpo['response_format'])) {
            unset($corpo['response_format']);
            try {
                $resposta = $this->consultar($url, $corpo);
            } catch (ConnectionException $e) {
                Log::warning('Assistente: a IA não respondeu.', ['erro' => $e->getMessage()]);

                return null;
            }
        }

        if (! $resposta->successful()) {
            Log::warning('Assistente: a IA recusou a consulta.', ['status' => $resposta->status()]);

            return null;
        }

        $conteudo = $resposta->json('choices.0.message.content');
        if (is_array($conteudo)) {
            $conteudo = implode('', array_map(
                fn ($parte) => is_string($parte) ? $parte : (string) ($parte['text'] ?? ''),
                $conteudo,
            ));
        }
        if (! is_string($conteudo) || $conteudo === '') {
            return null;
        }

        return $this->leitura($conteudo, $frase, $porChave);
    }

    /** Aviso da tela: null quando a IA está desligada. */
    public function aviso(): ?string
    {
        if (! $this->ativa()) {
            return null;
        }

        return $this->local()
            ? 'Se a frase sair do comum, eu tento entender neste computador, sem enviar para fora.'
            : 'Se a frase sair do comum, ela é enviada à IA configurada para eu escolher a rotina.';
    }

    private function ativa(): bool
    {
        return filter_var(config('assistente.ia.ativa'), FILTER_VALIDATE_BOOLEAN);
    }

    private function local(): bool
    {
        $host = strtolower((string) parse_url((string) config('assistente.ia.url'), PHP_URL_HOST));

        return in_array($host, self::LOCAIS, true);
    }

    /** URL do chat. Só http e https; null se o endereço não servir. */
    private function endereco(): ?string
    {
        $url = rtrim(trim((string) config('assistente.ia.url')), '/');
        $partes = parse_url($url);
        if (! in_array($partes['scheme'] ?? '', ['http', 'https'], true) || ($partes['host'] ?? '') === '') {
            return null;
        }

        return str_ends_with($url, '/chat/completions') ? $url : $url.'/chat/completions';
    }

    /**
     * @param  array<string, mixed>  $corpo
     */
    private function consultar(string $url, array $corpo): Response
    {
        $pedido = Http::acceptJson()
            ->asJson()
            ->connectTimeout(2)
            ->timeout($this->timeout())
            ->withoutRedirecting();

        $chave = trim((string) config('assistente.ia.chave'));
        if ($chave !== '') {
            $pedido = $pedido->withToken($chave);
        }

        return $pedido->post($url, $corpo);
    }

    /** @return array<string, mixed> */
    private function corpo(string $modelo, string $instrucoes, string $frase): array
    {
        return [
            'model' => $modelo,
            'temperature' => 0,
            'max_tokens' => 120,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $instrucoes],
                ['role' => 'user', 'content' => $frase],
            ],
        ];
    }

    /**
     * @param  list<string>  $chaves
     * @param  list<Comando>  $comandos
     */
    private function instrucoes(array $chaves, array $comandos): string
    {
        $linhas = [];
        foreach ($comandos as $comando) {
            if (! in_array($comando->chave(), $chaves, true)) {
                continue;
            }
            $linhas[] = $comando->chave().' | '.$comando->titulo().' | '.$comando->atalho().' | '.implode(' / ', $comando->exemplos());
        }

        $lista = implode("\n", $linhas);

        return <<<TXT
        Você só escolhe uma rotina do ERP e reescreve o pedido no formato de um exemplo. Não executa nada e não inventa dados.

        Rotinas (chave | nome | atalho | exemplos):
        {$lista}

        Responda apenas um JSON: {"chave":"...","frase":"..."} ou {"chave":null,"frase":null}.
        A frase deve parecer um dos exemplos, com nomes, números, datas e documentos copiados do pedido.
        Não crie número, data, documento, e-mail ou nome que a pessoa não disse.
        Não converta número por extenso em dígito.
        Se não for nenhuma destas rotinas, use null.
        TXT;
    }

    /**
     * @param  array<string, true>  $permitidas
     * @return array{chave: string, frase: string}|null
     */
    private function leitura(string $conteudo, string $original, array $permitidas): ?array
    {
        $dados = $this->objetoJson($conteudo);
        if ($dados === null) {
            return null;
        }

        $chave = $dados['chave'] ?? null;
        $frase = $dados['frase'] ?? null;
        if (! is_string($chave) || $chave === '' || $chave === 'null' || ! isset($permitidas[$chave])) {
            return null;
        }
        if (! is_string($frase) || $frase === '' || $frase === 'null') {
            return null;
        }

        $frase = Leitor::limpar($frase);
        if ($frase === '' || mb_strlen($frase) > 300 || $this->inventouDado($original, $frase)) {
            return null;
        }

        return ['chave' => $chave, 'frase' => $frase];
    }

    /** @return array<string, mixed>|null */
    private function objetoJson(string $texto): ?array
    {
        $texto = trim($texto);
        if (str_starts_with($texto, '```')) {
            $texto = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/iu', '', $texto));
        }

        $dados = json_decode($texto, true);
        if (is_array($dados)) {
            return $dados;
        }

        $inicio = strpos($texto, '{');
        $fim = strrpos($texto, '}');
        if ($inicio === false || $fim === false || $fim <= $inicio) {
            return null;
        }

        $dados = json_decode(substr($texto, $inicio, $fim - $inicio + 1), true);

        return is_array($dados) ? $dados : null;
    }

    /** Número ou e-mail que não estava na frase da pessoa. */
    private function inventouDado(string $original, string $frase): bool
    {
        $digitos = preg_replace('/\D/', '', $original) ?? '';
        preg_match_all('/\d+/', $frase, $numeros);
        foreach ($numeros[0] as $numero) {
            if (! str_contains($digitos, $numero)) {
                return true;
            }
        }

        $base = mb_strtolower($original);
        preg_match_all('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $frase, $emails);
        foreach ($emails[0] as $email) {
            if (! str_contains($base, mb_strtolower($email))) {
                return true;
            }
        }

        return false;
    }

    private function timeout(): int
    {
        return max(2, min(20, (int) config('assistente.ia.timeout', 12)));
    }
}
