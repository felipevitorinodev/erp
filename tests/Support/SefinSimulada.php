<?php

namespace Tests\Support;

use App\Services\Nfses\NfseBuilderService;
use App\Services\Nfses\NfseSenderService;
use DOMDocument;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Sefin Nacional de mentira para os testes: recebe a DPS gzip+base64 que o sistema enviou,
 * confere no XSD oficial do leiaute e devolve uma NFS-e no formato da API (JSON com o XML compactado).
 * Registrada com Http::fake(); nada sai da máquina.
 */
class SefinSimulada
{
    /** autorizada | rejeitada | duplicada | sem_resposta | instavel */
    public string $modo = 'autorizada';

    public string $codigoRejeicao = 'E0312';

    public string $motivoRejeicao = 'Código de tributação nacional não administrado pelo município de incidência.';

    /** ativo | inativo | ausente (404) */
    public string $convenio = 'ativo';

    /** @var list<string> método e caminho de cada chamada */
    public array $chamadas = [];

    /** @var list<string> DPS recebidas (XML) */
    public array $dps = [];

    /** @var list<string> pedidos de evento recebidos (XML) */
    public array $eventos = [];

    /** @var list<string> erros de XSD das DPS recebidas */
    public array $errosXsd = [];

    /** @var array<string, array{chave: string, xml: string}> NFS-e geradas por Id da DPS */
    public array $geradas = [];

    private int $numero = 0;

    public function registrar(): self
    {
        Http::fake(fn (Request $request) => $this->responder($request));

        return $this;
    }

    private function responder(Request $request)
    {
        $caminho = (string) parse_url($request->url(), PHP_URL_PATH);
        $this->chamadas[] = $request->method().' '.$caminho;

        if ($this->modo === 'sem_resposta' && $request->method() === 'POST') {
            return Http::failedConnection('timeout');
        }

        if ($this->modo === 'instavel') {
            return Http::response('Service Unavailable', 503);
        }

        if ($request->method() === 'POST' && preg_match('#/nfse$#', $caminho)) {
            return $this->emitir((string) ($request->data()['dpsXmlGZipB64'] ?? ''));
        }

        if ($request->method() === 'POST' && preg_match('#/nfse/([0-9A-Z]{50})/eventos$#', $caminho, $m)) {
            $xml = NfseSenderService::descompactar((string) ($request->data()['pedidoRegistroEventoXmlGZipB64'] ?? ''));
            $this->eventos[] = $xml;

            return Http::response([
                'tipoAmbiente' => 2,
                'dataHoraProcessamento' => now()->toIso8601String(),
                'eventoXmlGZipB64' => NfseSenderService::compactar('<evento xmlns="'.NfseBuilderService::NAMESPACE.'" versao="1.01"><infEvento Id="EVT'.$m[1].'101101001"><nSeqEvento>1</nSeqEvento>'.$this->semDeclaracao($xml).'</infEvento></evento>'),
            ], 201);
        }

        if ($request->method() === 'GET' && preg_match('#/dps/(DPS[0-9A-Z]+)$#', $caminho, $m)) {
            if (! isset($this->geradas[$m[1]])) {
                return Http::response(['erros' => [['Codigo' => 'E404', 'Descricao' => 'DPS não encontrada']]], 404);
            }

            return Http::response(['idDps' => $m[1], 'chaveAcesso' => $this->geradas[$m[1]]['chave']], 200);
        }

        if ($request->method() === 'GET' && preg_match('#/nfse/([0-9A-Z]{50})$#', $caminho, $m)) {
            foreach ($this->geradas as $gerada) {
                if ($gerada['chave'] === $m[1]) {
                    return Http::response(['chaveAcesso' => $m[1], 'nfseXmlGZipB64' => NfseSenderService::compactar($gerada['xml'])], 200);
                }
            }

            return Http::response(['erros' => [['Codigo' => 'E404', 'Descricao' => 'NFS-e não encontrada']]], 404);
        }

        if ($request->method() === 'GET' && str_contains($caminho, '/parametros_municipais/')) {
            if ($this->convenio === 'ausente') {
                return Http::response(['mensagem' => 'Município não encontrado'], 404);
            }

            return Http::response(['parametrosConvenio' => [
                'aderenteAmbienteNacional' => $this->convenio === 'ativo' ? 1 : 0,
                'aderenteEmissorNacional' => 1,
            ]], 200);
        }

        return Http::response(['erros' => [['Codigo' => 'X', 'Descricao' => 'Rota não simulada: '.$caminho]]], 404);
    }

    private function emitir(string $compactado)
    {
        $xml = NfseSenderService::descompactar($compactado);
        $this->dps[] = $xml;
        $this->validarXsd($xml);

        if ($this->modo === 'rejeitada') {
            return Http::response([
                'tipoAmbiente' => 2,
                'erros' => [['Codigo' => $this->codigoRejeicao, 'Descricao' => $this->motivoRejeicao, 'Complemento' => null]],
            ], 400);
        }

        $id = $this->idDps($xml);
        $gerada = $this->geradas[$id] ?? $this->gerar($id, $xml);

        if ($this->modo === 'duplicada') {
            return Http::response([
                'erros' => [['Codigo' => 'E0014', 'Descricao' => 'Conjunto de Série, Número, Código do Município Emissor e CNPJ/CPF informado nesta DPS já existe em uma NFS-e.']],
            ], 400);
        }

        return Http::response([
            'tipoAmbiente' => 2,
            'versaoAplicativo' => 'SefinNacional_simulada',
            'dataHoraProcessamento' => now()->toIso8601String(),
            'idDps' => $id,
            'chaveAcesso' => $gerada['chave'],
            'nfseXmlGZipB64' => NfseSenderService::compactar($gerada['xml']),
            'alertas' => null,
        ], 201);
    }

    /**
     * @return array{chave: string, xml: string}
     */
    public function gerar(string $id, string $xmlDps): array
    {
        $this->numero++;
        $cMun = substr($id, 3, 7);
        $inscricao = substr($id, 11, 14);
        $chave = $cMun.'1'.'2'.$inscricao.str_pad((string) $this->numero, 13, '0', STR_PAD_LEFT).now()->format('ym').'123456789'.'0';
        $dps = $this->semDeclaracao($xmlDps);

        $nfse = '<NFSe xmlns="'.NfseBuilderService::NAMESPACE.'" versao="1.01"><infNFSe Id="NFS'.$chave.'">'
            .'<xLocEmi>São Paulo</xLocEmi><xLocPrestacao>São Paulo</xLocPrestacao><nNFSe>'.$this->numero.'</nNFSe>'
            .'<cLocIncid>'.$cMun.'</cLocIncid><xLocIncid>São Paulo</xLocIncid>'
            .'<xTribNac>Serviço de teste</xTribNac><verAplic>SefinNacional_simulada</verAplic><ambGer>2</ambGer><tpEmis>1</tpEmis>'
            .'<cStat>100</cStat><dhProc>'.now()->format('Y-m-d\TH:i:sP').'</dhProc><nDFSe>'.$this->numero.'</nDFSe>'
            .'<emit><CNPJ>'.$inscricao.'</CNPJ><xNome>Empresa Teste LTDA</xNome><enderNac><xLgr>Praça da Sé</xLgr><nro>1</nro><xBairro>Sé</xBairro><cMun>'.$cMun.'</cMun><UF>SP</UF><CEP>01001000</CEP></enderNac></emit>'
            .'<valores><vBC>90.00</vBC><pAliqAplic>2.00</pAliqAplic><vISSQN>1.80</vISSQN><vLiq>90.00</vLiq></valores>'
            .$dps.'</infNFSe></NFSe>';

        return $this->geradas[$id] = ['chave' => $chave, 'xml' => $nfse];
    }

    private function idDps(string $xml): string
    {
        return preg_match('/Id="(DPS[0-9A-Z]+)"/', $xml, $m) ? $m[1] : 'DPS';
    }

    private function semDeclaracao(string $xml): string
    {
        return (string) preg_replace('/^<\?xml[^>]*\?>/', '', trim($xml));
    }

    private function validarXsd(string $xml): void
    {
        $leiaute = str_contains($xml, '<finNFSe>0</finNFSe><cLocEmi>') ? '20261020' : '20260209';
        $pasta = config('nfse.leiautes')[$leiaute];
        $dom = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        $dom->loadXML($xml);
        if (! $dom->schemaValidate(resource_path('nfse/schemas/'.$pasta.'/DPS_v1.01.xsd'))) {
            foreach (libxml_get_errors() as $erro) {
                $this->errosXsd[] = trim($erro->message);
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
    }
}
