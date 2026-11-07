<?php

namespace App\Services\Estoque;

use Carbon\Carbon;
use NFePHP\Common\Keys;
use NFePHP\Common\Strings;
use NFePHP\Common\Validator;
use RuntimeException;
use SimpleXMLElement;

class NfeXmlParser
{
    /**
     * Parse NF-e XML content and extract all relevant data.
     *
     * @param string $xmlContent Raw XML content
     * @return array Parsed data with keys: nota, emitente, destinatario, totais, itens
     * @throws RuntimeException
     */
    public function parse(string $xmlContent): array
    {
        // Step A — Validate that the content is XML
        if (!Validator::isXML($xmlContent)) {
            throw new RuntimeException('Arquivo inválido: o conteúdo não é um XML válido.');
        }

        // Step B — Load with SimpleXMLElement and register the NF-e namespace
        $xml = new SimpleXMLElement($xmlContent);
        $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');

        // Detect structure: bare NFe or authorized nfeProc
        $infNFe = $this->encontrarInfNFe($xml);

        if ($infNFe === null) {
            throw new RuntimeException('XML inválido: estrutura de NF-e não reconhecida.');
        }

        // Step C — Extract chave de acesso
        $chaveAcesso = $this->extrairChaveAcesso($infNFe);

        // Step D — Extract all fields
        $ide = $infNFe->ide;
        $emit = $infNFe->emit;
        $dest = $infNFe->dest;
        $total = $infNFe->total;

        return [
            'nota' => [
                'chave_acesso'       => $chaveAcesso,
                'numero'             => $this->limparString((string) $ide->nNF),
                'serie'              => $this->limparString((string) $ide->serie),
                'data_emissao'       => $this->parseDataEmissao((string) $ide->dhEmi),
                'natureza_operacao'  => $this->limparString((string) $ide->natOp),
            ],
            'emitente' => [
                'cnpj'         => $this->extrairDigitos((string) $emit->CNPJ),
                'razao_social' => $this->limparString((string) $emit->xNome),
                'ie'           => $this->limparString((string) $emit->IE),
            ],
            'destinatario' => [
                'cnpj'         => $this->extrairDigitos((string) $dest->CNPJ),
                'razao_social' => $this->limparString((string) $dest->xNome),
            ],
            'totais' => [
                'subtotal' => $this->toFloat($total->ICMSTot->vProd),
                'desconto' => $this->toFloat($total->ICMSTot->vDesc, 0.0),
                'total'    => $this->toFloat($total->ICMSTot->vNF),
            ],
            'itens' => $this->extrairItens($infNFe),
        ];
    }

    /**
     * Find infNFe element from either bare NFe or nfeProc structure.
     */
    private function encontrarInfNFe(SimpleXMLElement $xml): ?SimpleXMLElement
    {
        // Try bare NFe structure (root is NFe)
        if ($xml->getName() === 'NFe' && isset($xml->infNFe)) {
            return $xml->infNFe;
        }

        // Try authorized structure (root is nfeProc, NFe is child)
        if ($xml->getName() === 'nfeProc' && isset($xml->NFe->infNFe)) {
            return $xml->NFe->infNFe;
        }

        // Try with namespace
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces[''])) {
            $xml->registerXPathNamespace('nfe', $namespaces['']);

            // Try nfeProc/NFe/infNFe
            $result = $xml->xpath('//nfe:infNFe');
            if (!empty($result)) {
                return $result[0];
            }
        }

        // Try without namespace prefix for direct children
        if (isset($xml->NFe->infNFe)) {
            return $xml->NFe->infNFe;
        }

        if (isset($xml->infNFe)) {
            return $xml->infNFe;
        }

        return null;
    }

    /**
     * Extract and validate the 44-digit chave de acesso from infNFe Id attribute.
     */
    private function extrairChaveAcesso(SimpleXMLElement $infNFe): string
    {
        $id = (string) $infNFe['Id'];

        // Remove "NFe" prefix if present
        $chave = preg_replace('/^NFe/', '', $id);

        if (empty($chave) || !Keys::isValid($chave)) {
            throw new RuntimeException('XML inválido: chave de acesso ausente ou incorreta.');
        }

        return $chave;
    }

    /**
     * Parse date from dhEmi field (handles timezone offset).
     */
    private function parseDataEmissao(string $dhEmi): ?string
    {
        if (empty($dhEmi)) {
            return null;
        }

        try {
            return Carbon::parse($dhEmi)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Extract items from det elements.
     */
    private function extrairItens(SimpleXMLElement $infNFe): array
    {
        $itens = [];

        foreach ($infNFe->det as $det) {
            $prod = $det->prod;
            $ean = $this->limparString((string) $prod->cEAN);

            // EAN is null if 'SEM GTIN' or empty
            if (empty($ean) || strtoupper($ean) === 'SEM GTIN') {
                $ean = null;
            }

            $itens[] = [
                'numero_item'                => (int) $det['nItem'],
                'codigo_produto_fornecedor'  => $this->limparString((string) $prod->cProd),
                'descricao'                  => $this->limparString((string) $prod->xProd),
                'ean'                        => $ean,
                'ncm'                        => $this->limparString((string) $prod->NCM),
                'cfop'                       => $this->limparString((string) $prod->CFOP),
                'unidade'                    => $this->limparString((string) $prod->uCom),
                'quantidade'                 => $this->toFloat($prod->qCom),
                'preco_unitario'             => $this->toFloat($prod->vUnCom),
                'desconto_item'              => $this->toFloat($prod->vDesc, 0.0),
                'total_item'                 => $this->toFloat($prod->vProd),
            ];
        }

        return $itens;
    }

    /**
     * Clean string using NFePHP Strings utility.
     */
    private function limparString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Strings::replaceUnacceptableCharacters(trim($value));
    }

    /**
     * Extract only digits from a string (for CNPJ/CPF).
     */
    private function extrairDigitos(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_replace('/\D/', '', $value);
    }

    /**
     * Convert SimpleXMLElement or string to float.
     */
    private function toFloat(mixed $value, float $default = 0.0): float
    {
        if ($value === null || (string) $value === '') {
            return $default;
        }

        return (float) $value;
    }
}
