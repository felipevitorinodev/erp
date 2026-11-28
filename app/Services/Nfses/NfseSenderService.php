<?php

namespace App\Services\Nfses;

use App\Support\MensagemPublica;
use DOMDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NFePHP\Common\Certificate;
use Throwable;

/**
 * Comunicação com a Sefin Nacional (API REST, autenticação mútua com o certificado A1).
 * Todo retorno vira um array com 'status':
 *  - autorizada / cancelada: a Sefin confirmou;
 *  - rejeitada: a Sefin recusou e listou os erros;
 *  - duplicada: a DPS já gerou NFS-e (E0014), falta só buscar a nota;
 *  - nao_encontrada: a consulta não achou a DPS;
 *  - erro: sem resposta conclusiva (rede, tempo esgotado, retorno ilegível). A situação real é desconhecida.
 */
class NfseSenderService
{
    public function __construct(private Certificate $certificado) {}

    /**
     * @return array<string, mixed>
     */
    public function emitir(string $xmlAssinado, int $ambiente): array
    {
        $resposta = $this->enviar($ambiente, 'post', '/nfse', [
            'dpsXmlGZipB64' => self::compactar($xmlAssinado),
        ]);

        if (isset($resposta['status'])) {
            return $resposta;
        }

        /** @var Response $resposta */
        $json = self::json($resposta);

        if ($resposta->successful() && filled(self::campo($json, 'nfseXmlGZipB64'))) {
            return $this->resultadoNfse($json);
        }

        return $this->resultadoErro($resposta, $json);
    }

    /**
     * Situação da DPS pelo Id: devolve a chave quando já virou NFS-e.
     *
     * @return array<string, mixed>
     */
    public function consultarDps(string $idDps, int $ambiente): array
    {
        $resposta = $this->enviar($ambiente, 'get', '/dps/'.rawurlencode($idDps));

        if (isset($resposta['status'])) {
            return $resposta;
        }

        /** @var Response $resposta */
        if ($resposta->status() === 404) {
            return ['status' => 'nao_encontrada', 'motivo' => 'A DPS não consta na Sefin Nacional.'];
        }

        $json = self::json($resposta);
        $chave = (string) self::campo($json, 'chaveAcesso');

        if ($resposta->successful() && $chave !== '') {
            return $this->consultarNfse($chave, $ambiente);
        }

        return $this->resultadoErro($resposta, $json, rejeita: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function consultarNfse(string $chave, int $ambiente): array
    {
        $resposta = $this->enviar($ambiente, 'get', '/nfse/'.rawurlencode($chave));

        if (isset($resposta['status'])) {
            return $resposta;
        }

        /** @var Response $resposta */
        if ($resposta->status() === 404) {
            return ['status' => 'nao_encontrada', 'motivo' => 'A NFS-e não consta na Sefin Nacional.'];
        }

        $json = self::json($resposta);
        if ($resposta->successful() && filled(self::campo($json, 'nfseXmlGZipB64'))) {
            return $this->resultadoNfse($json + ['chaveAcesso' => $chave]);
        }

        return $this->resultadoErro($resposta, $json, rejeita: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelar(string $chave, string $xmlEventoAssinado, int $ambiente): array
    {
        $resposta = $this->enviar($ambiente, 'post', '/nfse/'.rawurlencode($chave).'/eventos', [
            'pedidoRegistroEventoXmlGZipB64' => self::compactar($xmlEventoAssinado),
        ]);

        if (isset($resposta['status'])) {
            return $resposta;
        }

        /** @var Response $resposta */
        $json = self::json($resposta);
        $evento = (string) self::campo($json, 'eventoXmlGZipB64');

        if ($resposta->successful() && $evento !== '') {
            return [
                'status' => 'cancelada',
                'xml_evento' => self::descompactar($evento),
                'motivo' => 'Cancelamento registrado.',
            ];
        }

        return $this->resultadoErro($resposta, $json);
    }

    /**
     * Parâmetros do convênio do município com o Sistema Nacional NFS-e.
     *
     * @return array{status: string, situacao?: string|null, detalhes?: array<string, mixed>, motivo?: string}
     */
    public function convenio(string $codigoMunicipio, int $ambiente): array
    {
        $resposta = $this->enviar($ambiente, 'get', '/parametros_municipais/'.rawurlencode($codigoMunicipio).'/convenio');

        if (isset($resposta['status'])) {
            return $resposta;
        }

        /** @var Response $resposta */
        if ($resposta->status() === 404) {
            return ['status' => 'ok', 'situacao' => 'inativo', 'detalhes' => [], 'motivo' => 'O município não tem convênio com o Sistema Nacional NFS-e.'];
        }

        $json = $resposta->json();
        if (! $resposta->successful() || ! is_array($json)) {
            return $this->resultadoErro($resposta, is_array($json) ? $json : []);
        }

        $detalhes = self::achatar($json);
        $situacao = null;
        foreach ($detalhes as $chave => $valor) {
            if (stripos($chave, 'aderenteAmbienteNacional') !== false || stripos($chave, 'situacaoConvenio') !== false) {
                $situacao = in_array(strtolower((string) $valor), ['1', 'true', 'ativo', 's', 'sim'], true) ? 'ativo' : 'inativo';
                break;
            }
        }

        return ['status' => 'ok', 'situacao' => $situacao, 'detalhes' => $detalhes];
    }

    public static function compactar(string $xml): string
    {
        $conteudo = str_starts_with(ltrim($xml), '<?xml') ? $xml : '<?xml version="1.0" encoding="UTF-8"?>'.$xml;

        return base64_encode((string) gzencode($conteudo));
    }

    public static function descompactar(string $base64): string
    {
        $binario = base64_decode($base64, true);
        if ($binario === false) {
            return '';
        }

        $xml = @gzdecode($binario);

        return $xml === false ? $binario : $xml;
    }

    /**
     * Dados da NFS-e devolvida: chave (Id "NFS" + 50), número, data de processamento e o XML.
     *
     * @return array<string, mixed>
     */
    public static function lerNfse(string $xml): array
    {
        $dados = ['chave' => null, 'numero' => null, 'data' => null, 'xml' => $xml];
        $dom = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);

        try {
            if (! $dom->loadXML($xml, LIBXML_NONET)) {
                return $dados;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }

        $inf = $dom->getElementsByTagName('infNFSe')->item(0);
        if ($inf instanceof \DOMElement) {
            $id = (string) $inf->getAttribute('Id');
            $dados['chave'] = preg_match('/(\d{6}[0-9A-Z]{14}\d{30})$/', $id, $m) ? $m[1] : null;
        }
        $dados['numero'] = $dom->getElementsByTagName('nNFSe')->item(0)?->textContent;
        $dados['data'] = $dom->getElementsByTagName('dhProc')->item(0)?->textContent;

        return $dados;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function resultadoNfse(array $json): array
    {
        $xml = self::descompactar((string) self::campo($json, 'nfseXmlGZipB64'));
        $lido = self::lerNfse($xml);

        return [
            'status' => 'autorizada',
            'chave' => $lido['chave'] ?: (string) self::campo($json, 'chaveAcesso'),
            'numero' => $lido['numero'],
            'data' => $lido['data'],
            'xml_nfse' => $xml,
            'motivo' => 'NFS-e gerada.',
            'alertas' => self::mensagens(self::campo($json, 'alertas')),
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function resultadoErro(Response $resposta, array $json, bool $rejeita = true): array
    {
        $erros = self::mensagens(self::campo($json, 'erros') ?? self::campo($json, 'erro'));

        if ($erros !== []) {
            $codigos = array_column($erros, 'codigo');
            $primeiro = $erros[0];

            return [
                'status' => in_array('E0014', $codigos, true) ? 'duplicada' : ($rejeita && $resposta->clientError() ? 'rejeitada' : 'erro'),
                'codigo' => $primeiro['codigo'],
                'motivo' => $primeiro['texto'],
                'mensagens' => $erros,
            ];
        }

        return [
            'status' => 'erro',
            'codigo' => (string) $resposta->status(),
            'motivo' => 'A Sefin Nacional respondeu HTTP '.$resposta->status().' sem detalhar o erro.',
        ];
    }

    /**
     * @return list<array{codigo: string, texto: string}>
     */
    private static function mensagens(mixed $lista): array
    {
        if (! is_array($lista)) {
            return [];
        }

        if (! array_is_list($lista)) {
            $lista = [$lista];
        }

        $saida = [];
        foreach ($lista as $item) {
            if (! is_array($item)) {
                continue;
            }
            $codigo = trim((string) (self::campo($item, 'codigo') ?? ''));
            $texto = trim((string) (self::campo($item, 'descricao') ?? self::campo($item, 'mensagem') ?? ''));
            $complemento = trim((string) (self::campo($item, 'complemento') ?? ''));
            if ($complemento !== '' && $complemento !== $texto) {
                $texto = trim($texto.' '.$complemento);
            }
            if ($codigo !== '' || $texto !== '') {
                $saida[] = ['codigo' => $codigo, 'texto' => mb_substr($texto, 0, 500)];
            }
        }

        return $saida;
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(Response $resposta): array
    {
        try {
            $json = $resposta->json();
        } catch (Throwable) {
            return [];
        }

        return is_array($json) ? $json : [];
    }

    /** Lê um campo do JSON sem depender da caixa (a API alterna "Codigo" e "codigo"). */
    private static function campo(array $json, string $nome): mixed
    {
        foreach ($json as $chave => $valor) {
            if (is_string($chave) && strcasecmp($chave, $nome) === 0) {
                return $valor;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function achatar(array $dados, string $prefixo = ''): array
    {
        $saida = [];
        foreach ($dados as $chave => $valor) {
            $nome = $prefixo === '' ? (string) $chave : $prefixo.'.'.$chave;
            if (is_array($valor)) {
                $saida += self::achatar($valor, $nome);
            } else {
                $saida[$nome] = is_bool($valor) ? ($valor ? 'true' : 'false') : $valor;
            }
        }

        return $saida;
    }

    /**
     * @param  array<string, mixed>  $corpo
     * @return Response|array<string, mixed>
     */
    private function enviar(int $ambiente, string $metodo, string $caminho, array $corpo = []): Response|array
    {
        $base = rtrim((string) config('nfse.sefin.'.$ambiente), '/');
        $pasta = storage_path('app/nfse-tmp');
        $certificado = $pasta.'/'.Str::random(32).'.pem';
        $chave = $pasta.'/'.Str::random(32).'.pem';

        try {
            if (! is_dir($pasta)) {
                mkdir($pasta, 0700, true);
            }

            $cadeia = (string) $this->certificado->publicKey;
            foreach ($this->certificado->chainKeys->listChain() as $intermediario) {
                $cadeia .= "\n".$intermediario;
            }
            file_put_contents($certificado, $cadeia);
            file_put_contents($chave, (string) $this->certificado->privateKey);
            chmod($certificado, 0600);
            chmod($chave, 0600);

            $cliente = $this->cliente($certificado, $chave);
            $resposta = $metodo === 'post'
                ? $cliente->post($base.$caminho, $corpo)
                : $cliente->get($base.$caminho);

            if ($resposta->serverError()) {
                return [
                    'status' => 'erro',
                    'codigo' => (string) $resposta->status(),
                    'motivo' => 'A Sefin Nacional está instável (HTTP '.$resposta->status().'). Tente consultar em alguns minutos.',
                ];
            }

            return $resposta;
        } catch (ConnectionException $e) {
            Log::warning('NFS-e: sem resposta da Sefin Nacional', ['caminho' => $caminho, 'erro' => MensagemPublica::de($e)]);

            return ['status' => 'erro', 'motivo' => 'Sem resposta da Sefin Nacional (conexão ou tempo esgotado).'];
        } catch (Throwable $e) {
            Log::error('NFS-e: falha na comunicação com a Sefin Nacional', ['caminho' => $caminho, 'erro' => MensagemPublica::de($e)]);

            return ['status' => 'erro', 'motivo' => 'Falha na comunicação com a Sefin Nacional.'];
        } finally {
            @unlink($certificado);
            @unlink($chave);
        }
    }

    private function cliente(string $certificado, string $chave): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout((int) config('nfse.timeout', 30))
            ->connectTimeout((int) config('nfse.connect_timeout', 10))
            ->withOptions([
                'cert' => $certificado,
                'ssl_key' => $chave,
                'verify' => (bool) config('nfse.verificar_ssl', true),
            ]);
    }
}
