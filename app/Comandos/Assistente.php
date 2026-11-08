<?php

namespace App\Comandos;

use App\Models\Usuarios\User;
use App\Support\Auditoria;
use Illuminate\Support\Str;

/**
 * Conduz a conversa: entende sim e não, responde perguntas pendentes, cumprimenta,
 * encaminha o pedido para a rotina e grava só depois da confirmação.
 */
final class Assistente
{
    /** Respostas curtas de "sim": todas as palavras precisam estar aqui e ao menos uma ser forte. */
    private const SIM = '/^(?=.*\b(?:sim|s|ss|isso|ok|okay|pode|confirm\w*|certo|correto|claro|manda|beleza|blz|positivo|uhum|aham|bora|fechado|perfeito|exato|salva|salvar|grava|gravar|ta)\b)'
        .'(?:sim|s|ss|isso|ok|okay|pode|confirm\w*|certo|correto|claro|manda|beleza|blz|positivo|uhum|aham|bora|fechado|perfeito|exato|salva|salvar|grava|gravar|ta|mesmo|ai|ser|esta)'
        .'(?:\s+(?:sim|s|isso|ok|pode|confirm\w*|certo|correto|claro|manda|beleza|perfeito|exato|salva|salvar|grava|gravar|ta|mesmo|ai|ser|esta))*$/u';

    private const NAO = '/^(?=.*\b(?:nao|n|cancel\w*|esquece|esqueca|deixa|pare|desist\w*|negativo|errado)\b)'
        .'(?:nao|n|cancel\w*|esquece|esqueca|deixa|pra|para|la|nada|pare|desist\w*|negativo|quero|melhor|errado|ta|esta|mais)'
        .'(?:\s+(?:nao|n|cancel\w*|esquece|esqueca|deixa|pra|para|la|nada|pare|desist\w*|negativo|quero|melhor|errado|ta|esta|mais))*$/u';

    private const AJUDA = '/^(?:ajuda|help|socorro|menu|opcoes|comandos|o que (?:voce|vc) (?:faz|sabe fazer|consegue fazer|pode fazer)|o que da (?:pra|para) fazer|como (?:funciona|usar|te uso|voce funciona)|quem (?:e|eh) (?:voce|vc))$/u';

    private const SAUDACAO = '/^(?<saudacao>oi|ola|opa|e ai|eai|bom dia|boa tarde|boa noite|hey|hello|salve)(?:\s+(?:assistente|tudo bem|tudo bom|td bem|tudo certo))*$/u';

    private const AGRADECIMENTO = '/^(?:muito\s+)?(?:obrigad[oa]|obg|brigad[oa]|valeu|vlw|show|otimo|massa|top|legal|maravilha)(?:\s+(?:mesmo|demais))?$/u';

    /** "é no pix", "foi 2 coca": palavras que só introduzem a resposta. */
    private const ENCHIMENTO = '/^(?:e|eh|foi|era|sao|seria|sera|vai\s+ser|acho\s+que(?:\s+e)?)\s+(?<resto>.+)$/u';

    /** Pedidos incompletos que servem para mais de uma rotina: pergunta qual. */
    private const AMBIGUOS = [
        '/^(?:cadastr|registr|cri|adicion|inclu|salv)\w*(?:\s+(?:um|uma|novo|nova|o|a))?(?:\s+(?:cadastro|registro|pessoa|contato))?$/u' => ['Cadastrar o quê?', ['cliente', 'fornecedor']],
        '/^(?:(?:lanc|registr|cri|adicion|inclu)\w*\s+)?(?:uma\s+|nova\s+|a\s+)?(?:contas?|lancamentos?|financeiro)$/u' => ['Que tipo de conta?', ['conta_receber', 'conta_pagar']],
        '/^(?:consult|ver|mostr|busc|procur|pesquis)\w*$/u' => ['O que você quer consultar?', ['produto', 'resumo']],
    ];

    private const ATALHOS_NA_SAUDACAO = 4;

    public function __construct(
        private readonly Interpretador $interpretador,
        private readonly RegistroComandos $registro,
        private readonly Conversa $conversa,
    ) {}

    public function responder(string $frase, User $usuario): Resposta
    {
        $texto = Leitor::limpar($frase);
        $bruto = self::simplificar($texto);
        $curto = self::simplificar(Interpretador::semCortesia($texto));
        $temPrevia = $this->conversa->temPrevia();
        $pergunta = $this->conversa->pergunta();

        if (preg_match(self::SIM, $curto)) {
            if ($temPrevia) {
                return $this->confirmar(null, $usuario);
            }
            if ($pergunta === null) {
                return Resposta::info('Certo! É só me dizer o que precisa.');
            }
        }

        if (preg_match(self::NAO, $curto)) {
            if ($temPrevia || $pergunta !== null) {
                $this->conversa->esquecer();

                return $temPrevia
                    ? Resposta::info('Tudo bem, não salvei nada.')->encerrandoPrevia('cancelada')
                    : Resposta::info('Tudo bem, deixei de lado.');
            }

            return Resposta::info('Tudo bem. Se precisar, é só escrever.');
        }

        if ($bruto === '' || preg_match(self::AJUDA, $bruto)) {
            return $this->ajuda($usuario);
        }

        if ($pergunta !== null) {
            $novoPedido = $this->interpretador->reconhecer($texto, tolerante: false);
            if ($novoPedido === null) {
                return $this->responderPergunta($pergunta, $texto, $usuario);
            }
            $this->conversa->esquecer();

            return $this->atender($novoPedido['comando'], $novoPedido['argumento'], $usuario);
        }

        if (preg_match(self::SAUDACAO, $bruto, $m)) {
            return $this->saudar($m['saudacao'], $usuario);
        }

        if (preg_match(self::AGRADECIMENTO, $bruto)) {
            return Resposta::info('Por nada! Se precisar, é só escrever.');
        }

        $pedido = $this->interpretador->reconhecer($texto);
        if ($pedido !== null) {
            return $this->atender($pedido['comando'], $pedido['argumento'], $usuario);
        }

        return $this->ambiguo($curto, $usuario) ?? $this->naoEntendi($texto, $usuario);
    }

    /** Grava a prévia. Sem token, vale a prévia pendente (resposta "sim"). */
    public function confirmar(?string $token, User $usuario): Resposta
    {
        $pendente = $this->conversa->retirarPrevia($token);
        if ($pendente === null) {
            return Resposta::erro('Essa confirmação expirou ou já foi usada. Peça de novo.')->encerrandoPrevia('encerrada');
        }

        $comando = $this->registro->buscar($pendente['comando']);
        if ($comando === null || ! $usuario->pode($comando->permissao())) {
            return Resposta::erro('Seu perfil não tem mais acesso a esta rotina.')->encerrandoPrevia('encerrada');
        }

        $resposta = $comando->executar($pendente['dados']);

        if ($registro = $resposta->registro()) {
            Auditoria::registrar('comando.executado', $registro['entidade'], $registro['id'], ['comando' => $comando->chave()]);
        }

        return $resposta->encerrandoPrevia($resposta->tipo === 'sucesso' ? 'confirmada' : 'encerrada');
    }

    public function cancelar(string $token): Resposta
    {
        $this->conversa->retirarPrevia($token);

        return Resposta::info('Tudo bem, não salvei nada.')->encerrandoPrevia('cancelada');
    }

    public function ajuda(User $usuario): Resposta
    {
        return Resposta::info(
            'Posso ajudar com estas tarefas. Toque numa delas ou escreva do seu jeito.',
            opcoes: $this->atalhos($this->registro->permitidos($usuario)),
        );
    }

    private function atender(Comando $comando, string $argumento, User $usuario): Resposta
    {
        if (! $usuario->pode($comando->permissao())) {
            return Resposta::erro('Seu perfil não pode usar “'.$comando->titulo().'”. Se precisar, peça a liberação ao administrador.');
        }

        return $this->lembrar($comando, $argumento, $comando->preparar($argumento));
    }

    /** A resposta completa a frase guardada; a rotina lê tudo de novo. */
    private function responderPergunta(array $pergunta, string $texto, User $usuario): Resposta
    {
        $comando = $this->registro->buscar($pergunta['comando']);
        $this->conversa->esquecer();

        if ($comando === null || ! $usuario->pode($comando->permissao())) {
            return Resposta::erro('Seu perfil não tem mais acesso a esta rotina.');
        }

        $limpa = Interpretador::semCortesia($texto);
        $resposta = Leitor::grupos(self::ENCHIMENTO, $limpa)['resto'] ?? $limpa;
        $argumento = $comando->completar($pergunta['argumento'], $pergunta['campo'], $resposta !== '' ? $resposta : $texto, $pergunta['termo']);
        $resultado = $comando->preparar($argumento);

        // A rotina recusou a resposta (a frase não mudou) e perguntou a mesma coisa.
        if ($argumento === $pergunta['argumento'] && $resultado->tipo === 'pergunta') {
            $resultado = $resultado->comTextoAntes('Não entendi. ');
        }

        return $this->lembrar($comando, $argumento, $resultado);
    }

    /** Prévia e pergunta ficam na sessão; as demais respostas não mudam o que está pendente. */
    private function lembrar(Comando $comando, string $argumento, Resposta $resposta): Resposta
    {
        if ($resposta->precisaConfirmar()) {
            return $resposta->comToken($this->conversa->guardarPrevia($comando->chave(), $resposta->dados()));
        }

        if ($pergunta = $resposta->perguntaPendente()) {
            $this->conversa->guardarPergunta($comando->chave(), $argumento, $pergunta['campo'], $pergunta['termo']);
        }

        return $resposta;
    }

    private function saudar(string $saudacao, User $usuario): Resposta
    {
        $inicio = str_starts_with($saudacao, 'bo') ? Str::ucfirst($saudacao) : 'Oi';
        $nome = Str::of((string) $usuario->name)->before(' ');

        return Resposta::info(
            $inicio.($nome->isNotEmpty() ? ', '.$nome : '').'! Em que posso ajudar?',
            opcoes: $this->atalhos(array_slice($this->registro->permitidos($usuario), 0, self::ATALHOS_NA_SAUDACAO)),
        );
    }

    private function ambiguo(string $curto, User $usuario): ?Resposta
    {
        foreach (self::AMBIGUOS as $regex => [$pergunta, $chaves]) {
            if (! preg_match($regex, $curto)) {
                continue;
            }

            $comandos = array_values(array_filter(
                $this->registro->permitidos($usuario),
                fn (Comando $c) => in_array($c->chave(), $chaves, true),
            ));

            if (count($comandos) === 1) {
                return $this->atender($comandos[0], '', $usuario);
            }
            if ($comandos !== []) {
                return Resposta::info($pergunta, opcoes: $this->atalhos($comandos));
            }
        }

        return null;
    }

    private function naoEntendi(string $texto, User $usuario): Resposta
    {
        $provaveis = $this->interpretador->provaveis($texto, $usuario);

        return $provaveis !== []
            ? Resposta::info('Não entendi bem. Você quer fazer alguma destas?', opcoes: $this->atalhos($provaveis))
            : Resposta::info('Não entendi. Escreva de outro jeito ou escolha uma opção:', opcoes: $this->atalhos($this->registro->permitidos($usuario)));
    }

    /**
     * @param  list<Comando>  $comandos
     * @return list<array{rotulo: string, enviar: string}>
     */
    private function atalhos(array $comandos): array
    {
        return array_map(fn (Comando $c) => Resposta::opcao($c->titulo(), $c->atalho()), $comandos);
    }

    /** Minúsculo, sem acento e sem pontuação: "Sim, pode!" → "sim pode". */
    private static function simplificar(string $texto): string
    {
        return trim((string) preg_replace('/[\s,;:!.?]+/u', ' ', Leitor::normalizar($texto)));
    }
}
