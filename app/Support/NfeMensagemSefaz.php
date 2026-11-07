<?php

namespace App\Support;

/**
 * Traduz códigos que esta rotina já trata. O motivo original da SEFAZ continua gravado na nota.
 */
class NfeMensagemSefaz
{
    public static function explicar(?string $codigo): ?string
    {
        return match ($codigo) {
            '100', '150' => 'A SEFAZ autorizou o uso da nota.',
            '101', '135', '151', '155' => 'A SEFAZ registrou o cancelamento (ou o evento já estava registrado).',
            '103', '105' => 'O lote ainda está em processamento. Use Consultar SEFAZ; não emita de novo.',
            '110', '205', '301', '302', '303' => 'Uso denegado. O número foi consumido e não pode ser reaproveitado. Guarde o XML.',
            '204' => 'Esta chave já foi autorizada. Consulte a NF-e em vez de emitir outra.',
            '217' => 'A SEFAZ não encontrou esta chave. A nota não chegou a ser autorizada e pode ser emitida de novo.',
            '225' => 'O XML foi recusado por erro de schema. Corrija o campo indicado na mensagem técnica e reenvie o mesmo número.',
            '228', '502' => 'A data ou a hora de emissão não foi aceita. Confira o fuso da UF e se o relógio do servidor está certo.',
            '539' => 'Este número já foi usado em outra chave. A próxima emissão precisa de um número novo.',
            '573' => 'Este cancelamento já foi registrado. Atualize a tela; se a nota já estiver cancelada, não envie de novo.',
            '611' => 'O código de barras (GTIN) do item é inválido. Corrija o EAN ou apague o campo.',
            '778' => 'O NCM do item não foi aceito. Confira os 8 dígitos com o cadastro do produto.',
            default => null,
        };
    }
}
