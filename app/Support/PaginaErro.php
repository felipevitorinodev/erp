<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Conteúdo das telas de erro HTTP (resources/views/errors).
 *
 * Não consulta banco, sessão nem usuário: a tela precisa abrir mesmo com o sistema fora do ar.
 * A mensagem técnica da exceção nunca é exibida (pode conter SQL, caminho de arquivo ou dado de cliente).
 */
final class PaginaErro
{
    private const GENERICA = [
        'ilustracao' => 'generico',
        'titulo' => 'Não foi possível concluir',
        'texto' => 'O pedido não pôde ser atendido. Volte e tente de novo.',
        'acoes' => ['voltar', 'inicio'],
    ];

    private const PAGINAS = [
        401 => [
            'ilustracao' => 'sessao-expirada',
            'titulo' => 'Entre para continuar',
            'texto' => 'Você precisa estar conectado para abrir esta página.',
            'acoes' => ['entrar'],
        ],
        403 => [
            'ilustracao' => 'acesso-negado',
            'titulo' => 'Você não tem acesso a esta tela',
            'texto' => 'Seu perfil não inclui esta área. Se precisar dela no dia a dia, peça a liberação ao administrador do sistema.',
            'acoes' => ['voltar', 'inicio'],
        ],
        404 => [
            'ilustracao' => 'nao-encontrado',
            'titulo' => 'Página não encontrada',
            'texto' => 'O endereço pode ter sido digitado errado, ou o registro foi excluído ou pertence a outra empresa.',
            'acoes' => ['voltar', 'inicio'],
        ],
        413 => [
            'ilustracao' => 'arquivo-grande',
            'titulo' => 'Arquivo grande demais',
            'texto' => 'O arquivo enviado passa do tamanho aceito. Envie um arquivo menor ou compacte antes de enviar.',
            'acoes' => ['voltar'],
        ],
        419 => [
            'ilustracao' => 'sessao-expirada',
            'titulo' => 'A página expirou',
            'texto' => 'Ela ficou aberta muito tempo sem uso. Volte, atualize a página e envie de novo.',
            'acoes' => ['voltar', 'inicio'],
        ],
        429 => [
            'ilustracao' => 'muitas-tentativas',
            'titulo' => 'Muitas tentativas seguidas',
            'texto' => 'Por segurança, o sistema pausou novas tentativas por alguns instantes.',
            'acoes' => ['tentar'],
            'espera' => 60,
        ],
        500 => [
            'ilustracao' => 'erro-servidor',
            'titulo' => 'Algo deu errado do nosso lado',
            'texto' => 'Não foi nada que você fez. Tente de novo em instantes; se continuar, avise o suporte informando o horário abaixo.',
            'acoes' => ['recarregar', 'inicio'],
            'horario' => true,
        ],
        503 => [
            'ilustracao' => 'manutencao',
            'titulo' => 'Sistema em manutenção',
            'texto' => 'Estamos fazendo uma atualização rápida. A página tenta abrir de novo sozinha.',
            'acoes' => ['recarregar'],
            'espera' => 30,
            'recarregar' => true,
        ],
    ];

    /**
     * @return array{codigo:int, ilustracao:string, titulo:string, texto:string, acoes:list<string>, espera:?int, recarregar:bool, horario:bool}
     */
    public static function para(?Throwable $excecao, int $padrao = 500): array
    {
        $codigo = $excecao instanceof HttpExceptionInterface ? $excecao->getStatusCode() : $padrao;
        $pagina = self::PAGINAS[$codigo] ?? ($codigo >= 500 ? self::PAGINAS[500] : self::GENERICA);
        $cabecalhos = $excecao instanceof HttpExceptionInterface ? $excecao->getHeaders() : [];

        return [
            'codigo' => $codigo,
            'ilustracao' => $pagina['ilustracao'],
            'titulo' => $pagina['titulo'],
            'texto' => $pagina['texto'],
            'acoes' => $pagina['acoes'],
            'espera' => isset($pagina['espera']) ? self::espera($cabecalhos, $pagina['espera']) : null,
            'recarregar' => $pagina['recarregar'] ?? false,
            'horario' => $pagina['horario'] ?? false,
        ];
    }

    /** Usa o Retry-After em segundos quando vier no cabeçalho; limita para não travar a tela por horas. */
    private static function espera(array $cabecalhos, int $padrao): int
    {
        $valor = $cabecalhos['Retry-After'] ?? $cabecalhos['retry-after'] ?? null;
        $segundos = is_numeric($valor) ? (int) $valor : $padrao;

        return max(5, min($segundos, 600));
    }
}
