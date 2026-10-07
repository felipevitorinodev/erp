<?php

namespace App\Services;

use App\Models\Empresas\Empresa;
use Illuminate\Support\Facades\Log;
use NFePHP\Common\Keys;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Complements;
use NFePHP\NFe\Tools;
use RuntimeException;
use Throwable;

class NfeSenderService
{
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
        } catch (Throwable $e) {
            Log::error('SEFAZ response inesperado', [
                'empresa_id' => $empresa->id,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status' => 'erro',
                'motivo' => $this->mensagemAmigavel($e->getMessage()),
            ];
        }
    }

    public function cancelar(string $chave, string $justificativa, string $protocolo): array
    {
        try {
            $response = $this->tools->sefazCancela($chave, $justificativa, $protocolo);
            $std = (new Standardize($response))->toStd();

            $infEvento = data_get($std, 'retEvento.infEvento');
            if (is_array($infEvento)) {
                $infEvento = $infEvento[0] ?? null;
            }

            $cStat = (string) (data_get($infEvento, 'cStat') ?? data_get($std, 'cStat') ?? '');
            $xMotivo = (string) (data_get($infEvento, 'xMotivo') ?? data_get($std, 'xMotivo') ?? 'Retorno sem motivo.');

            if (in_array($cStat, ['101', '135', '155'], true)) {
                return [
                    'status' => 'cancelada',
                    'codigo' => $cStat,
                    'motivo' => $xMotivo,
                    'xml_retorno' => $response,
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
                'exception' => $e->getMessage(),
            ]);

            return [
                'status' => 'erro',
                'motivo' => $this->mensagemAmigavel($e->getMessage()),
            ];
        }
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
                    'status' => 'rejeitada',
                    'codigo' => $cStat,
                    'motivo' => $xMotivo,
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
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        Log::error('SEFAZ response inesperado', [
            'empresa_id' => $this->empresa->id,
            'nRec' => $nRec,
            'response' => $ultimo,
        ]);

        return [
            'status' => 'erro',
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
        $infProt = $this->extrairInfProt($std);

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
                    'exception' => $e->getMessage(),
                    'response' => $response,
                ]);

                return [
                    'status' => 'erro',
                    'motivo' => 'A nota foi autorizada, mas o protocolo não pôde ser anexado ao XML.',
                ];
            }
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
            $infProt = $std ? $this->extrairInfProt($std) : null;

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

    private function extrairInfProt(object $std): ?object
    {
        $prot = data_get($std, 'protNFe');

        if (is_array($prot)) {
            $prot = $prot[0] ?? null;
        }

        if (is_object($prot) && isset($prot->infProt)) {
            return $prot->infProt;
        }

        if (isset($std->infProt) && is_object($std->infProt)) {
            return $std->infProt;
        }

        return null;
    }

    private function padronizar(string $response): ?object
    {
        try {
            return (new Standardize($response))->toStd();
        } catch (Throwable $e) {
            Log::error('SEFAZ response inesperado', [
                'empresa_id' => $this->empresa->id,
                'exception' => $e->getMessage(),
                'response' => $response,
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
}
