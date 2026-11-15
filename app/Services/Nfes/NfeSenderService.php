<?php

namespace App\Services\Nfes;

use App\Models\Empresas\Empresa;
use Illuminate\Support\Facades\Log;
use NFePHP\Common\Exception\ValidatorException;
use NFePHP\Common\Keys;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Complements;
use NFePHP\NFe\Tools;
use RuntimeException;
use Throwable;

class NfeSenderService
{
    private const CSTAT_DENEGADA = ['110', '205', '301', '302', '303'];

    private Tools $tools;

    public function __construct(private readonly Empresa $empresa, ?Tools $tools = null)
    {
        $this->tools = $tools ?? NfeBuilderService::criarTools($empresa);
    }

    public function enviar(string $signedXml, Empresa $empresa): array
    {
        try {
            $nfeLote = str_pad((string) ((int) (microtime(true) * 1000) % 1000000000000000), 15, '0', STR_PAD_LEFT);
            $response = $this->tools->sefazEnviaLote([$signedXml], $nfeLote, 1);

            return $this->processarRespostaEnvio($signedXml, $response);
        } catch (ValidatorException $e) {
            // Falha de schema acontece antes do envio: a nota não chegou à SEFAZ.
            return [
                'status' => 'rejeitada',
                'motivo' => mb_substr('XML inválido: '.$e->getMessage(), 0, 255),
            ];
        } catch (Throwable $e) {
            Log::error('SEFAZ response inesperado', [
                'empresa_id' => $empresa->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return [
                'status' => 'erro',
                'motivo' => $this->mensagemAmigavel($e->getMessage()),
            ];
        }
    }

    public function cancelar(string $chave, string $justificativa, string $protocolo, int $ambiente): array
    {
        try {
            $this->tools->setEnvironment($ambiente);
            $response = $this->tools->sefazCancela($chave, $justificativa, $protocolo);
            $std = (new Standardize($response))->toStd();

            $infEvento = data_get($std, 'retEvento.infEvento');
            if (is_array($infEvento)) {
                $infEvento = $infEvento[0] ?? null;
            }

            $cStat = (string) (data_get($infEvento, 'cStat') ?? data_get($std, 'cStat') ?? '');
            $xMotivo = (string) (data_get($infEvento, 'xMotivo') ?? data_get($std, 'xMotivo') ?? 'Retorno sem motivo.');

            if (in_array($cStat, ['101', '135', '155'], true)) {
                try {
                    $xmlEvento = Complements::toAuthorize($this->tools->lastRequest, $response);
                } catch (Throwable $e) {
                    Log::error('SEFAZ response inesperado', [
                        'empresa_id' => $this->empresa->id,
                        'exception' => mb_substr($e->getMessage(), 0, 200),
                    ]);
                    $xmlEvento = $response;
                }

                return [
                    'status' => 'cancelada',
                    'codigo' => $cStat,
                    'motivo' => $xMotivo,
                    'xml_evento' => $xmlEvento,
                ];
            }

            return [
                'status' => 'erro',
                'codigo' => $cStat,
                'motivo' => $xMotivo,
            ];
        } catch (Throwable $e) {
            Log::error('SEFAZ response inesperado', [
                'empresa_id' => $this->empresa->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return [
                'status' => 'erro',
                'motivo' => $this->mensagemAmigavel($e->getMessage()),
            ];
        }
    }

    /**
     * Carta de Correção (evento 110110). 135/136 = registrada; o xCondUso fixo vem da biblioteca.
     * 573 (duplicidade) quer dizer que a SEFAZ já tem esse nSeqEvento: use cartasRegistradas() para trazer.
     *
     * @return array{status: string, codigo?: string, motivo: string, protocolo?: string, data_registro?: string, correcao?: string, xml_evento?: string}
     */
    public function cartaCorrecao(string $chave, string $correcao, int $sequencia, int $ambiente): array
    {
        try {
            $this->tools->setEnvironment($ambiente);
            $response = $this->tools->sefazCCe($chave, $correcao, $sequencia);
            $std = (new Standardize($response))->toStd();

            $infEvento = data_get($std, 'retEvento.infEvento');
            if (is_array($infEvento)) {
                $infEvento = $infEvento[0] ?? null;
            }

            $cStat = (string) (data_get($infEvento, 'cStat') ?? data_get($std, 'cStat') ?? '');
            $xMotivo = (string) (data_get($infEvento, 'xMotivo') ?? data_get($std, 'xMotivo') ?? 'Retorno sem motivo.');

            if (! in_array($cStat, ['135', '136'], true)) {
                return [
                    'status' => 'erro',
                    'codigo' => $cStat,
                    'motivo' => $xMotivo,
                ];
            }

            try {
                $xmlEvento = Complements::toAuthorize($this->tools->lastRequest, $response);
            } catch (Throwable $e) {
                Log::error('SEFAZ response inesperado', [
                    'empresa_id' => $this->empresa->id,
                    'exception' => mb_substr($e->getMessage(), 0, 200),
                ]);
                $xmlEvento = $response;
            }

            return [
                'status' => 'registrada',
                'codigo' => $cStat,
                'motivo' => $xMotivo,
                'protocolo' => (string) data_get($infEvento, 'nProt', ''),
                'data_registro' => (string) data_get($infEvento, 'dhRegEvento', ''),
                'correcao' => self::textoDaCorrecao($this->tools->lastRequest) ?? $correcao,
                'xml_evento' => $xmlEvento,
            ];
        } catch (Throwable $e) {
            Log::error('SEFAZ response inesperado', [
                'empresa_id' => $this->empresa->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return [
                'status' => 'erro',
                'motivo' => $this->mensagemAmigavel($e->getMessage()),
            ];
        }
    }

    /**
     * Cartas de Correção que a SEFAZ tem para a chave (consulta da NF-e traz os procEventoNFe).
     * Usado quando o envio volta com 573: o registro existe lá, mas a resposta não chegou aqui.
     *
     * @return list<array{sequencia: int, correcao: string, protocolo: string, codigo: string, motivo: string, data_registro: string, xml: string}>
     */
    public function cartasRegistradas(string $chave, int $ambiente): array
    {
        try {
            $this->tools->setEnvironment($ambiente);
            $response = $this->tools->sefazConsultaChave($chave);
        } catch (Throwable $e) {
            Log::error('SEFAZ response inesperado', [
                'empresa_id' => $this->empresa->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return [];
        }

        $dom = new \DOMDocument;
        if (@$dom->loadXML($response) === false) {
            return [];
        }

        $cartas = [];
        foreach ($dom->getElementsByTagName('procEventoNFe') as $proc) {
            $evento = $proc->getElementsByTagName('infEvento')->item(0);
            $retorno = $proc->getElementsByTagName('retEvento')->item(0)?->getElementsByTagName('infEvento')->item(0);
            $texto = fn (?\DOMElement $no, string $tag) => trim((string) $no?->getElementsByTagName($tag)->item(0)?->nodeValue);

            if ($texto($evento, 'tpEvento') !== '110110' || ! in_array($texto($retorno, 'cStat'), ['135', '136'], true)) {
                continue;
            }

            $documento = new \DOMDocument('1.0', 'UTF-8');
            $documento->appendChild($documento->importNode($proc, true));

            $cartas[] = [
                'sequencia' => (int) $texto($evento, 'nSeqEvento'),
                'correcao' => $texto($evento, 'xCorrecao'),
                'protocolo' => $texto($retorno, 'nProt'),
                'codigo' => $texto($retorno, 'cStat'),
                'motivo' => $texto($retorno, 'xMotivo'),
                'data_registro' => $texto($retorno, 'dhRegEvento'),
                'xml' => (string) $documento->saveXML(),
            ];
        }

        return $cartas;
    }

    /** xCorrecao como foi enviado (a biblioteca troca caracteres não aceitos e corta em 1000). */
    private static function textoDaCorrecao(string $request): ?string
    {
        return preg_match('/<xCorrecao>(.*?)<\/xCorrecao>/s', $request, $m) === 1
            ? html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8')
            : null;
    }

    /**
     * Consulta a situação de uma NF-e pela chave, para resolver notas que ficaram pendentes
     * (timeout, lote em processamento, falha ao anexar protocolo, duplicidade).
     */
    public function consultar(string $chave, string $signedXml, int $ambiente): array
    {
        try {
            $this->tools->setEnvironment($ambiente);
            $response = $this->tools->sefazConsultaChave($chave);
        } catch (Throwable $e) {
            Log::error('SEFAZ response inesperado', [
                'empresa_id' => $this->empresa->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return [
                'status' => 'erro',
                'motivo' => $this->mensagemAmigavel($e->getMessage()),
            ];
        }

        $std = $this->padronizar($response);
        if ($std === null) {
            return [
                'status' => 'erro',
                'motivo' => 'Não foi possível interpretar o retorno da SEFAZ.',
            ];
        }

        $cStat = (string) ($std->cStat ?? '');
        $xMotivo = (string) ($std->xMotivo ?? 'Retorno sem motivo.');

        if ($cStat === '217') {
            return [
                'status' => 'nao_encontrada',
                'codigo' => $cStat,
                'motivo' => $xMotivo,
            ];
        }

        if (in_array($cStat, ['101', '151', '155'], true)) {
            return [
                'status' => 'erro',
                'codigo' => $cStat,
                'motivo' => 'A SEFAZ informa que esta NF-e está cancelada: '.$xMotivo,
            ];
        }

        if ($this->extrairInfProt($std, $chave) === null) {
            return [
                'status' => 'erro',
                'codigo' => $cStat,
                'motivo' => $xMotivo,
            ];
        }

        return $this->resultadoProtocolo($signedXml, $std, $response, $cStat, $xMotivo);
    }

    private function processarRespostaEnvio(string $signedXml, string $response): array
    {
        $std = $this->padronizar($response);
        if ($std === null) {
            return [
                'status' => 'erro',
                'motivo' => 'Não foi possível interpretar o retorno da SEFAZ.',
            ];
        }

        $cStat = (string) ($std->cStat ?? '');
        $xMotivo = (string) ($std->xMotivo ?? 'Retorno sem motivo.');

        if (in_array($cStat, ['103', '104', '105'], true) && empty(data_get($std, 'protNFe'))) {
            $nRec = (string) (data_get($std, 'infRec.nRec') ?? '');
            if ($nRec === '') {
                return [
                    'status' => 'erro',
                    'codigo' => $cStat,
                    'motivo' => 'Lote recebido sem protocolo nem recibo: '.$xMotivo,
                ];
            }

            return $this->consultarRecibo($signedXml, $nRec);
        }

        return $this->resultadoProtocolo($signedXml, $std, $response, $cStat, $xMotivo);
    }

    private function consultarRecibo(string $signedXml, string $nRec): array
    {
        $ultimo = null;

        for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
            sleep(2);

            try {
                $response = $this->tools->sefazConsultaRecibo($nRec);
                $std = $this->padronizar($response);
                $ultimo = $response;

                if ($std === null) {
                    continue;
                }

                $cStat = (string) ($std->cStat ?? '');
                $xMotivo = (string) ($std->xMotivo ?? 'Retorno sem motivo.');

                if (in_array($cStat, ['105'], true)) {
                    continue;
                }

                return $this->resultadoProtocolo($signedXml, $std, $response, $cStat, $xMotivo);
            } catch (Throwable $e) {
                Log::error('SEFAZ response inesperado', [
                    'empresa_id' => $this->empresa->id,
                    'nRec' => $nRec,
                    'exception' => mb_substr($e->getMessage(), 0, 200),
                ]);
            }
        }

        Log::error('SEFAZ response inesperado', [
            'empresa_id' => $this->empresa->id,
            'nRec' => $nRec,
        ]);

        return [
            'status' => 'erro',
            'recibo' => $nRec,
            'motivo' => 'A SEFAZ ainda está processando o lote. Tente consultar a NF-e novamente em instantes.',
        ];
    }

    private function resultadoProtocolo(
        string $signedXml,
        object $std,
        string $response,
        string $cStatLote,
        string $xMotivoLote
    ): array {
        $infProt = $this->extrairInfProt($std, $this->chaveDoXml($signedXml));

        if ($infProt === null) {
            if (in_array($cStatLote, ['100', '150'], true)) {
                return $this->autorizarComResposta($signedXml, $response, $cStatLote, $xMotivoLote);
            }

            return [
                'status' => 'rejeitada',
                'codigo' => $cStatLote,
                'motivo' => $xMotivoLote,
            ];
        }

        $cStat = (string) ($infProt->cStat ?? $cStatLote);
        $xMotivo = (string) ($infProt->xMotivo ?? $xMotivoLote);
        $chave = (string) ($infProt->chNFe ?? Keys::extractAccessKey($signedXml) ?? '');
        $protocolo = (string) ($infProt->nProt ?? '');

        if (in_array($cStat, ['100', '150'], true)) {
            if ($protocolo === '') {
                return [
                    'status' => 'erro',
                    'codigo' => $cStat,
                    'motivo' => 'A SEFAZ retornou autorização sem número de protocolo. Consulte a NF-e novamente.',
                ];
            }

            try {
                $xmlAutorizado = Complements::toAuthorize($signedXml, $response);

                return [
                    'status' => 'autorizada',
                    'chave' => $chave,
                    'protocolo' => $protocolo,
                    'xml_autorizado' => $xmlAutorizado,
                    'codigo' => $cStat,
                    'motivo' => $xMotivo,
                ];
            } catch (Throwable $e) {
                Log::error('SEFAZ response inesperado', [
                    'empresa_id' => $this->empresa->id,
                    'exception' => mb_substr($e->getMessage(), 0, 200),
                ]);

                return [
                    'status' => 'erro',
                    'codigo' => $cStat,
                    'motivo' => 'A nota foi autorizada, mas o protocolo não pôde ser anexado ao XML. Consulte a NF-e novamente.',
                ];
            }
        }

        if (in_array($cStat, self::CSTAT_DENEGADA, true)) {
            try {
                $xmlDenegado = Complements::toAuthorize($signedXml, $response);
            } catch (Throwable) {
                $xmlDenegado = null;
            }

            return [
                'status' => 'denegada',
                'chave' => $chave,
                'protocolo' => $protocolo,
                'xml_autorizado' => $xmlDenegado,
                'codigo' => $cStat,
                'motivo' => $xMotivo,
            ];
        }

        if ($cStat === '204') {
            // Duplicidade: esta mesma chave já foi recebida pela SEFAZ; a situação real vem da consulta.
            return [
                'status' => 'erro',
                'codigo' => $cStat,
                'motivo' => 'A SEFAZ informou duplicidade desta NF-e. Use "Consultar SEFAZ" para obter a situação real.',
            ];
        }

        return [
            'status' => 'rejeitada',
            'codigo' => $cStat,
            'motivo' => $xMotivo,
            'chave' => $chave !== '' ? $chave : null,
        ];
    }

    private function autorizarComResposta(
        string $signedXml,
        string $response,
        string $cStat,
        string $xMotivo
    ): array {
        try {
            $xmlAutorizado = Complements::toAuthorize($signedXml, $response);
            $chave = Keys::extractAccessKey($xmlAutorizado) ?: Keys::extractAccessKey($signedXml);
            $std = $this->padronizar($response);
            $infProt = $std ? $this->extrairInfProt($std, $this->chaveDoXml($signedXml)) : null;

            if ((string) ($infProt->nProt ?? '') === '') {
                return [
                    'status' => 'erro',
                    'codigo' => $cStat,
                    'motivo' => 'A SEFAZ retornou autorização sem número de protocolo. Consulte a NF-e novamente.',
                ];
            }

            return [
                'status' => 'autorizada',
                'chave' => (string) ($infProt->chNFe ?? $chave ?? ''),
                'protocolo' => (string) ($infProt->nProt ?? ''),
                'xml_autorizado' => $xmlAutorizado,
                'codigo' => $cStat,
                'motivo' => $xMotivo,
            ];
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Protocolo desta nota no retorno. Com mais de um protNFe (retorno de recibo de lote), escolhe pela chave:
     * pegar o primeiro gravaria a chave e o protocolo de outra nota.
     */
    private function extrairInfProt(object $std, string $chave = ''): ?object
    {
        $prot = data_get($std, 'protNFe');
        $protocolos = array_values(array_filter(
            is_array($prot) ? $prot : [$prot],
            fn ($item) => is_object($item) && isset($item->infProt) && is_object($item->infProt),
        ));

        if ($chave !== '') {
            foreach ($protocolos as $item) {
                if ((string) ($item->infProt->chNFe ?? '') === $chave) {
                    return $item->infProt;
                }
            }

            if (count($protocolos) > 1) {
                return null;
            }
        }

        if ($protocolos !== []) {
            return $protocolos[0]->infProt;
        }

        if (isset($std->infProt) && is_object($std->infProt)) {
            return $std->infProt;
        }

        return null;
    }

    private function chaveDoXml(string $xml): string
    {
        return preg_match('/Id="NFe(\d{44})"/', $xml, $m) === 1 ? $m[1] : '';
    }

    private function padronizar(string $response): ?object
    {
        try {
            return (new Standardize($response))->toStd();
        } catch (Throwable $e) {
            Log::error('SEFAZ response inesperado', [
                'empresa_id' => $this->empresa->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return null;
        }
    }

    private function mensagemAmigavel(string $mensagem): string
    {
        $mensagem = trim($mensagem);

        if ($mensagem === '') {
            return 'Falha na comunicação com a SEFAZ.';
        }

        if (str_contains(mb_strtolower($mensagem), 'certificate')
            || str_contains(mb_strtolower($mensagem), 'certificado')) {
            return 'Certificado digital inválido ou não configurado.';
        }

        return 'Falha na comunicação com a SEFAZ. Verifique o certificado, a UF e a conexão.';
    }

    /**
     * null quando cStat 107 (serviço em operação). Qualquer outra resposta bloqueia a emissão.
     */
    public function motivoSeIndisponivel(): ?string
    {
        try {
            $uf = strtoupper(trim((string) $this->empresa->uf));
            $ambiente = (int) ($this->empresa->nfe_ambiente ?: 2);
            $response = $this->tools->sefazStatus($uf, $ambiente, true);
            $std = $this->padronizar($response);
        } catch (Throwable $e) {
            Log::error('SEFAZ response inesperado', [
                'empresa_id' => $this->empresa->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return 'Não foi possível consultar o status da SEFAZ. A emissão foi bloqueada e nenhum número foi usado.';
        }

        return self::mensagemStatus(
            (string) ($std->cStat ?? ''),
            (string) ($std->xMotivo ?? '')
        );
    }

    public static function mensagemStatus(string $cStat, string $xMotivo): ?string
    {
        if ($cStat === '107') {
            return null;
        }

        $motivo = trim($xMotivo);
        $texto = $cStat === ''
            ? 'A SEFAZ não informou o status do serviço.'
            : 'A SEFAZ está indisponível (código '.$cStat.').';

        if ($motivo !== '') {
            $texto .= ' '.$motivo;
        }

        return $texto.' A emissão foi bloqueada e nenhum número foi usado. Contingência (SVC ou EPEC) não está habilitada: tpEmis permanece 1.';
    }

    /**
     * @return array{status: string, codigo: string, motivo: string, protocolo: ?string, xml: ?string}
     */
    public function inutilizar(int $serie, int $inicio, int $fim, string $justificativa, int $ambiente, string $ano): array
    {
        try {
            $this->tools->setEnvironment($ambiente);
            $response = $this->tools->sefazInutiliza($serie, $inicio, $fim, $justificativa, $ambiente, $ano);
            $std = $this->padronizar($response);
            $inf = data_get($std, 'infInut') ?? $std;
            $cStat = (string) (data_get($inf, 'cStat') ?? '');
            $xMotivo = (string) (data_get($inf, 'xMotivo') ?? 'Retorno sem motivo.');
            $protocolo = data_get($inf, 'nProt');

            if ($cStat === '102') {
                try {
                    $xml = Complements::toAuthorize($this->tools->lastRequest, $response);
                } catch (Throwable $e) {
                    Log::error('SEFAZ response inesperado', [
                        'empresa_id' => $this->empresa->id,
                        'exception' => mb_substr($e->getMessage(), 0, 200),
                    ]);
                    $xml = $response;
                }

                return [
                    'status' => 'autorizada',
                    'codigo' => $cStat,
                    'motivo' => $xMotivo,
                    'protocolo' => $protocolo ? (string) $protocolo : null,
                    'xml' => $xml,
                ];
            }

            return [
                'status' => 'rejeitada',
                'codigo' => $cStat,
                'motivo' => $xMotivo,
                'protocolo' => null,
                'xml' => null,
            ];
        } catch (Throwable $e) {
            Log::error('SEFAZ response inesperado', [
                'empresa_id' => $this->empresa->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return [
                'status' => 'rejeitada',
                'codigo' => '',
                'motivo' => $this->mensagemAmigavel($e->getMessage()),
                'protocolo' => null,
                'xml' => null,
            ];
        }
    }
}
