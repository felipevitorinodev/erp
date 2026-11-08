<?php

namespace Tests\Support;

use DOMDocument;
use DOMElement;
use NFePHP\Common\Exception\SoapException;
use NFePHP\Common\Soap\SoapBase;
use NFePHP\Common\Soap\SoapInterface;

/**
 * SEFAZ de mentira para os testes: recebe o pedido que o sped-nfe montou (já validado no XSD)
 * e devolve um retorno no formato real, ecoando chave, DigestValue, lote e faixa como a SEFAZ faz.
 * Registrada com app()->instance('nfe.soap', $sefaz); nada sai da máquina.
 */
class SefazSimulada extends SoapBase implements SoapInterface
{
    private const NS = 'http://www.portalfiscal.inf.br/nfe';

    /** autorizada | rejeitada | sem_resposta (o pedido chega, mas a resposta se perde) | fora (status 108) */
    public string $modo = 'autorizada';

    public string $codigoRejeicao = '391';

    public string $motivoRejeicao = 'Rejeicao: Nao informados os dados do cartao de credito / debito nas Formas de Pagamento da Nota Fiscal';

    /** Retorno do evento de cancelamento: 135 registrado, 501 fora do prazo. */
    public string $codigoEvento = '135';

    /** @var list<string> raiz de cada pedido recebido (enviNFe, consSitNFe, envEvento...) */
    public array $pedidos = [];

    /** @var list<string> XML de cada pedido recebido */
    public array $xmls = [];

    /** @var list<string> */
    public array $urls = [];

    /** @var array<string, array{digVal: string, nProt: string, cStat: string, xMotivo: string}> notas que a SEFAZ recebeu */
    private array $notas = [];

    private int $protocolo = 325260000000100;

    public function __construct()
    {
        parent::__construct(null);
    }

    public function send(
        $url,
        $operation = '',
        $action = '',
        $soapver = SOAP_1_2,
        $parameters = [],
        $namespaces = [],
        $request = '',
        $soapheader = null
    ) {
        $dom = new DOMDocument;
        $dom->loadXML($request);
        $pedido = $this->primeiroFilho($dom->documentElement);
        $raiz = $pedido->localName;

        $this->urls[] = (string) $url;
        $this->pedidos[] = $raiz;
        $this->xmls[] = $dom->saveXML($pedido);

        $retorno = match ($raiz) {
            'consStatServ' => $this->status($pedido),
            'enviNFe' => $this->autorizacao($pedido),
            'consSitNFe' => $this->consulta($pedido),
            'envEvento' => $this->evento($pedido),
            'inutNFe' => $this->inutilizacao($pedido),
            default => throw new \RuntimeException('Pedido não simulado: '.$raiz),
        };

        if ($this->modo === 'sem_resposta' && $raiz === 'enviNFe') {
            // A SEFAZ processou (a nota existe lá), mas a resposta não voltou: o caso da NFC-e pendente.
            throw SoapException::soapFault('Operation timed out after 30000 milliseconds [simulado]', 28);
        }

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope"><soap:Body>'
            .'<nfeResultMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/simulada">'.$retorno.'</nfeResultMsg>'
            .'</soap:Body></soap:Envelope>';
    }

    public function quantos(string $raiz): int
    {
        return count(array_filter($this->pedidos, fn (string $p) => $p === $raiz));
    }

    public function ultimo(string $raiz): ?string
    {
        foreach (array_reverse(array_keys($this->pedidos)) as $i) {
            if ($this->pedidos[$i] === $raiz) {
                return $this->xmls[$i];
            }
        }

        return null;
    }

    private function status(DOMElement $pedido): string
    {
        [$cStat, $xMotivo] = $this->modo === 'fora'
            ? ['108', 'Servico Paralisado Momentaneamente (curto prazo)']
            : ['107', 'Servico em Operacao'];

        return '<retConsStatServ versao="4.00" xmlns="'.self::NS.'">'
            .'<tpAmb>'.$this->valor($pedido, 'tpAmb').'</tpAmb><verAplic>SIMULADA</verAplic>'
            .'<cStat>'.$cStat.'</cStat><xMotivo>'.$xMotivo.'</xMotivo>'
            .'<cUF>'.$this->valor($pedido, 'cUF').'</cUF><dhRecbto>'.$this->agora().'</dhRecbto><tMed>1</tMed>'
            .'</retConsStatServ>';
    }

    private function autorizacao(DOMElement $pedido): string
    {
        $infNFe = $pedido->getElementsByTagName('infNFe')->item(0);
        $chave = substr((string) $infNFe->getAttribute('Id'), 3);
        $tpAmb = $this->valor($pedido, 'tpAmb');
        $digVal = $this->valor($pedido, 'DigestValue');

        if ($this->modo === 'rejeitada') {
            $prot = '<protNFe versao="4.00"><infProt><tpAmb>'.$tpAmb.'</tpAmb><verAplic>SIMULADA</verAplic>'
                .'<chNFe>'.$chave.'</chNFe><dhRecbto>'.$this->agora().'</dhRecbto>'
                .'<cStat>'.$this->codigoRejeicao.'</cStat><xMotivo>'.$this->motivoRejeicao.'</xMotivo></infProt></protNFe>';
        } else {
            $this->notas[$chave] = [
                'digVal' => $digVal,
                'nProt' => (string) $this->protocolo++,
                'cStat' => '100',
                'xMotivo' => 'Autorizado o uso da NF-e',
            ];
            $prot = $this->protNFe($chave, $tpAmb);
        }

        return '<retEnviNFe versao="4.00" xmlns="'.self::NS.'">'
            .'<tpAmb>'.$tpAmb.'</tpAmb><verAplic>SIMULADA</verAplic><cStat>104</cStat><xMotivo>Lote processado</xMotivo>'
            .'<cUF>'.substr($chave, 0, 2).'</cUF><dhRecbto>'.$this->agora().'</dhRecbto>'
            .$prot
            .'</retEnviNFe>';
    }

    private function consulta(DOMElement $pedido): string
    {
        $chave = $this->valor($pedido, 'chNFe');
        $tpAmb = $this->valor($pedido, 'tpAmb');
        $nota = $this->notas[$chave] ?? null;

        $corpo = $nota === null
            ? '<cStat>217</cStat><xMotivo>Rejeicao: NF-e nao consta na base de dados da SEFAZ</xMotivo><cUF>'.substr($chave, 0, 2).'</cUF>'
                .'<dhRecbto>'.$this->agora().'</dhRecbto><chNFe>'.$chave.'</chNFe>'
            : '<cStat>'.$nota['cStat'].'</cStat><xMotivo>'.$nota['xMotivo'].'</xMotivo><cUF>'.substr($chave, 0, 2).'</cUF>'
                .'<dhRecbto>'.$this->agora().'</dhRecbto><chNFe>'.$chave.'</chNFe>'.$this->protNFe($chave, $tpAmb);

        return '<retConsSitNFe versao="4.00" xmlns="'.self::NS.'">'
            .'<tpAmb>'.$tpAmb.'</tpAmb><verAplic>SIMULADA</verAplic>'.$corpo
            .'</retConsSitNFe>';
    }

    private function evento(DOMElement $pedido): string
    {
        $tpAmb = $this->valor($pedido, 'tpAmb');
        $chave = $this->valor($pedido, 'chNFe');
        $tpEvento = $this->valor($pedido, 'tpEvento');
        $aceito = $this->codigoEvento === '135';

        if ($aceito && isset($this->notas[$chave]) && $tpEvento === '110111') {
            $this->notas[$chave]['cStat'] = '101';
            $this->notas[$chave]['xMotivo'] = 'Cancelamento de NF-e homologado';
        }

        return '<retEnvEvento versao="1.00" xmlns="'.self::NS.'">'
            .'<idLote>'.$this->valor($pedido, 'idLote').'</idLote>'
            .'<tpAmb>'.$tpAmb.'</tpAmb><verAplic>SIMULADA</verAplic><cOrgao>'.substr($chave, 0, 2).'</cOrgao>'
            .'<cStat>128</cStat><xMotivo>Lote de Evento Processado</xMotivo>'
            .'<retEvento versao="1.00"><infEvento>'
            .'<tpAmb>'.$tpAmb.'</tpAmb><verAplic>SIMULADA</verAplic><cOrgao>'.substr($chave, 0, 2).'</cOrgao>'
            .'<cStat>'.$this->codigoEvento.'</cStat>'
            .'<xMotivo>'.($aceito ? 'Evento registrado e vinculado a NF-e' : 'Rejeicao: Prazo de Cancelamento superior ao previsto na Legislacao').'</xMotivo>'
            .'<chNFe>'.$chave.'</chNFe><tpEvento>'.$tpEvento.'</tpEvento><xEvento>Cancelamento</xEvento>'
            .'<nSeqEvento>'.$this->valor($pedido, 'nSeqEvento').'</nSeqEvento>'
            .'<dhRegEvento>'.$this->agora().'</dhRegEvento>'
            .($aceito ? '<nProt>'.$this->protocolo++.'</nProt>' : '')
            .'</infEvento></retEvento>'
            .'</retEnvEvento>';
    }

    private function inutilizacao(DOMElement $pedido): string
    {
        $campos = '';
        foreach (['cUF', 'ano', 'CNPJ', 'mod', 'serie', 'nNFIni', 'nNFFin'] as $campo) {
            $campos .= '<'.$campo.'>'.$this->valor($pedido, $campo).'</'.$campo.'>';
        }

        return '<retInutNFe versao="4.00" xmlns="'.self::NS.'"><infInut>'
            .'<tpAmb>'.$this->valor($pedido, 'tpAmb').'</tpAmb><verAplic>SIMULADA</verAplic>'
            .'<cStat>102</cStat><xMotivo>Inutilizacao de numero homologado</xMotivo>'
            .$campos
            .'<dhRecbto>'.$this->agora().'</dhRecbto><nProt>'.$this->protocolo++.'</nProt>'
            .'</infInut></retInutNFe>';
    }

    private function protNFe(string $chave, string $tpAmb): string
    {
        $nota = $this->notas[$chave];

        return '<protNFe versao="4.00"><infProt><tpAmb>'.$tpAmb.'</tpAmb><verAplic>SIMULADA</verAplic>'
            .'<chNFe>'.$chave.'</chNFe><dhRecbto>'.$this->agora().'</dhRecbto><nProt>'.$nota['nProt'].'</nProt>'
            .'<digVal>'.$nota['digVal'].'</digVal><cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo>'
            .'</infProt></protNFe>';
    }

    private function valor(DOMElement $pedido, string $tag): string
    {
        return (string) $pedido->getElementsByTagName($tag)->item(0)?->nodeValue;
    }

    private function primeiroFilho(DOMElement $elemento): DOMElement
    {
        foreach ($elemento->childNodes as $filho) {
            if ($filho instanceof DOMElement) {
                return $filho;
            }
        }

        return $elemento;
    }

    private function agora(): string
    {
        return now()->format('Y-m-d\TH:i:sP');
    }
}
