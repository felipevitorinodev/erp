<?php

namespace App\Services\Nfses;

use App\Models\Empresas\Empresa;
use App\Services\Nfes\NfeDatas;
use DOMDocument;
use DOMElement;
use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use RuntimeException;
use Throwable;

/**
 * Monta e assina a DPS (Declaração de Prestação de Serviço) e o pedido de registro de evento de cancelamento
 * da NFS-e Nacional, nos dois leiautes em uso: o de 09/02/2026 (produção) e o da NT 009, de 20/10/2026
 * (produção restrita). Todo XML é conferido no XSD oficial antes de sair.
 */
class NfseBuilderService
{
    public const NAMESPACE = 'http://www.sped.fazenda.gov.br/nfse';

    public const VERSAO = '1.01';

    public const LEIAUTE_PRODUCAO_ATUAL = '20260209';

    public const LEIAUTE_NT009 = '20261020';

    private ?Certificate $certificado = null;

    public function __construct(private Empresa $empresa) {}

    public static function leiaute(int $ambiente): string
    {
        $leiaute = (string) config('nfse.leiaute.'.$ambiente, self::LEIAUTE_NT009);

        return array_key_exists($leiaute, (array) config('nfse.leiautes')) ? $leiaute : self::LEIAUTE_NT009;
    }

    /**
     * Id da DPS: "DPS" + município emissor (7) + tipo de inscrição (1 = CPF, 2 = CNPJ) + inscrição (14)
     * + série (5) + número (15).
     */
    public static function idDps(string $codMunicipio, string $documento, string $serie, int $numero): string
    {
        $tipo = strlen($documento) === 11 ? '1' : '2';

        return 'DPS'
            .str_pad($codMunicipio, 7, '0', STR_PAD_LEFT)
            .$tipo
            .str_pad($documento, 14, '0', STR_PAD_LEFT)
            .str_pad($serie, 5, '0', STR_PAD_LEFT)
            .str_pad((string) $numero, 15, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $d  dados montados pelo NfseService (já validados pelo NfseRegras)
     * @return array{xml: string, id: string}
     */
    public function dps(array $d): array
    {
        $leiaute = (string) $d['leiaute'];
        $prest = $d['prestador'];
        $id = self::idDps($prest['cod_ibge'], $prest['documento'], (string) (int) $d['serie'], (int) $d['numero']);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $raiz = $dom->createElementNS(self::NAMESPACE, 'DPS');
        $raiz->setAttribute('versao', self::VERSAO);
        $dom->appendChild($raiz);

        $inf = $this->filho($raiz, 'infDPS');
        $inf->setAttribute('Id', $id);

        $this->filho($inf, 'tpAmb', (string) $d['ambiente']);
        $this->filho($inf, 'dhEmi', $d['emissao']->format('Y-m-d\TH:i:sP'));
        $this->filho($inf, 'verAplic', mb_substr((string) config('nfse.ver_aplic', 'ERP-NFSE'), 0, 20));
        $this->filho($inf, 'serie', (string) (int) $d['serie']);
        $this->filho($inf, 'nDPS', (string) (int) $d['numero']);
        $this->filho($inf, 'dCompet', $d['competencia']->format('Y-m-d'));
        $this->filho($inf, 'tpEmit', '1');
        if ($leiaute === self::LEIAUTE_NT009) {
            $this->filho($inf, 'finNFSe', '0');
        }
        $this->filho($inf, 'cLocEmi', $prest['cod_ibge']);

        $this->montarPrestador($inf, $prest);
        if ($d['tomador'] !== null) {
            $this->montarTomador($inf, $d['tomador']);
        }
        $this->montarServico($inf, $d['servico'], $leiaute);
        $this->montarValores($inf, $d);
        $this->montarIbsCbs($inf, $d['ibscbs'], $leiaute);

        $xml = $dom->saveXML($dom->documentElement) ?: '';
        $assinado = Signer::sign(
            $this->certificado(),
            $xml,
            'infDPS',
            'Id',
            OPENSSL_ALGO_SHA1,
            [true, false, null, null],
            'DPS'
        );

        $this->validarXsd($assinado, 'DPS_v1.01.xsd', $leiaute);

        return ['xml' => $assinado, 'id' => $id];
    }

    /**
     * Pedido de registro do evento 101101 (cancelamento pelo emitente).
     */
    public function cancelamento(string $chave, int $ambiente, string $codigoMotivo, string $motivo, string $leiaute): string
    {
        $documento = $this->documentoEmpresa();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $raiz = $dom->createElementNS(self::NAMESPACE, 'pedRegEvento');
        $raiz->setAttribute('versao', self::VERSAO);
        $dom->appendChild($raiz);

        $inf = $this->filho($raiz, 'infPedReg');
        $inf->setAttribute('Id', 'PRE'.$chave.'101101');
        $this->filho($inf, 'tpAmb', (string) $ambiente);
        $this->filho($inf, 'verAplic', mb_substr((string) config('nfse.ver_aplic', 'ERP-NFSE'), 0, 20));
        $this->filho($inf, 'dhEvento', now($this->fuso())->format('Y-m-d\TH:i:sP'));
        $this->filho($inf, strlen($documento) === 11 ? 'CPFAutor' : 'CNPJAutor', $documento);
        $this->filho($inf, 'chNFSe', $chave);
        $evento = $this->filho($inf, 'e101101');
        $this->filho($evento, 'xDesc', 'Cancelamento de NFS-e');
        $this->filho($evento, 'cMotivo', $codigoMotivo);
        $this->filho($evento, 'xMotivo', mb_substr(trim($motivo), 0, 255));

        $xml = $dom->saveXML($dom->documentElement) ?: '';
        $assinado = Signer::sign(
            $this->certificado(),
            $xml,
            'infPedReg',
            'Id',
            OPENSSL_ALGO_SHA1,
            [true, false, null, null],
            'pedRegEvento'
        );

        $this->validarXsd($assinado, 'pedRegEvento_v1.01.xsd', $leiaute);

        return $assinado;
    }

    /**
     * @throws RuntimeException com a primeira divergência do XSD, já em português.
     */
    public function validarXsd(string $xml, string $arquivo, string $leiaute): void
    {
        $pasta = (string) (config('nfse.leiautes')[$leiaute] ?? '');
        $schema = resource_path('nfse/schemas/'.$pasta.'/'.$arquivo);

        if ($pasta === '' || ! is_file($schema)) {
            throw new RuntimeException('Esquema XSD da NFS-e não encontrado para o leiaute '.$leiaute.'.');
        }

        $dom = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            if (! $dom->loadXML($xml, LIBXML_NONET)) {
                throw new RuntimeException('O XML da NFS-e ficou malformado.');
            }

            if (! $dom->schemaValidate($schema, LIBXML_NONET)) {
                $erros = array_map(
                    fn ($erro) => trim(preg_replace('/\{[^}]+\}/', '', $erro->message) ?? $erro->message),
                    libxml_get_errors()
                );

                throw new RuntimeException('A DPS não passou na validação do leiaute oficial: '.implode(' | ', array_slice(array_unique($erros), 0, 3)));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
    }

    public function certificado(): Certificate
    {
        if ($this->certificado instanceof Certificate) {
            return $this->certificado;
        }

        $caminho = $this->empresa->certificado_path ? storage_path('app/'.$this->empresa->certificado_path) : '';

        if ($caminho === '' || ! is_file($caminho) || blank($this->empresa->certificado_senha_decrypted)) {
            throw new RuntimeException('Configure o certificado digital A1 na empresa antes de emitir a NFS-e.');
        }

        try {
            $certificado = Certificate::readPfx((string) file_get_contents($caminho), (string) $this->empresa->certificado_senha_decrypted);
        } catch (Throwable) {
            throw new RuntimeException('O certificado digital da empresa é inválido ou a senha está incorreta.');
        }

        if ($certificado->isExpired()) {
            throw new RuntimeException('O certificado digital da empresa está vencido.');
        }

        return $this->certificado = $certificado;
    }

    public function fuso(): string
    {
        return (new NfeDatas)->fusoDaUf($this->empresa->uf);
    }

    private function documentoEmpresa(): string
    {
        $cnpj = preg_replace('/\D/', '', (string) $this->empresa->cnpj) ?? '';

        return strlen($cnpj) === 14 ? $cnpj : (preg_replace('/\D/', '', (string) $this->empresa->cpf) ?? '');
    }

    /**
     * @param  array<string, mixed>  $prest
     */
    private function montarPrestador(DOMElement $inf, array $prest): void
    {
        $no = $this->filho($inf, 'prest');
        $this->filho($no, strlen($prest['documento']) === 11 ? 'CPF' : 'CNPJ', $prest['documento']);
        if ($prest['im'] !== '') {
            $this->filho($no, 'IM', $prest['im']);
        }
        // Emitente prestador (tpEmit = 1): sem xNome (E0121) e sem endereço (E0128).
        if ($prest['fone'] !== null) {
            $this->filho($no, 'fone', $prest['fone']);
        }
        if ($prest['email'] !== null) {
            $this->filho($no, 'email', $prest['email']);
        }
        $reg = $this->filho($no, 'regTrib');
        $this->filho($reg, 'opSimpNac', (string) $prest['op_simp_nac']);
        if ($prest['reg_ap_trib_sn'] !== null) {
            $this->filho($reg, 'regApTribSN', (string) $prest['reg_ap_trib_sn']);
        }
        $this->filho($reg, 'regEspTrib', (string) $prest['reg_esp_trib']);
    }

    /**
     * @param  array<string, mixed>  $toma
     */
    private function montarTomador(DOMElement $inf, array $toma): void
    {
        $no = $this->filho($inf, 'toma');
        $this->filho($no, $toma['tipo'] === 'cpf' ? 'CPF' : 'CNPJ', $toma['documento']);
        if ($toma['im'] !== '') {
            $this->filho($no, 'IM', $toma['im']);
        }
        $this->filho($no, 'xNome', mb_substr($toma['nome'], 0, 300));

        if ($toma['endereco'] !== null) {
            $end = $this->filho($no, 'end');
            $nac = $this->filho($end, 'endNac');
            $this->filho($nac, 'cMun', $toma['endereco']['cod_ibge']);
            $this->filho($nac, 'CEP', $toma['endereco']['cep']);
            $this->filho($end, 'xLgr', $toma['endereco']['logradouro']);
            $this->filho($end, 'nro', $toma['endereco']['numero']);
            if ($toma['endereco']['complemento'] !== '') {
                $this->filho($end, 'xCpl', $toma['endereco']['complemento']);
            }
            $this->filho($end, 'xBairro', $toma['endereco']['bairro']);
        }

        if ($toma['fone'] !== null) {
            $this->filho($no, 'fone', $toma['fone']);
        }
        if ($toma['email'] !== null) {
            $this->filho($no, 'email', $toma['email']);
        }
    }

    /**
     * @param  array<string, mixed>  $serv
     */
    private function montarServico(DOMElement $inf, array $serv, string $leiaute): void
    {
        $no = $this->filho($inf, 'serv');
        $loc = $this->filho($no, 'locPrest');
        $this->filho($loc, 'cLocPrestacao', $serv['municipio_prestacao']);

        $c = $this->filho($no, 'cServ');
        $this->filho($c, 'cTribNac', $serv['c_trib_nac']);
        if ($serv['c_trib_mun'] !== '') {
            $this->filho($c, 'cTribMun', $serv['c_trib_mun']);
        }
        // Ordem do cServ: no leiaute de produção 20260209 o NBS vem depois da descrição; na NT 009, antes.
        $descricao = mb_substr(trim($serv['descricao']), 0, 2000);
        if ($leiaute === self::LEIAUTE_NT009) {
            $this->filho($c, 'cNBS', $serv['nbs']);
            $this->filho($c, 'xDescServ', $descricao);
        } else {
            $this->filho($c, 'xDescServ', $descricao);
            $this->filho($c, 'cNBS', $serv['nbs']);
        }
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function montarValores(DOMElement $inf, array $d): void
    {
        $no = $this->filho($inf, 'valores');
        $serv = $this->filho($no, 'vServPrest');
        $this->filho($serv, 'vServ', $this->valor($d['valores']['servico']));

        if ($d['valores']['desconto'] > 0) {
            $desc = $this->filho($no, 'vDescCondIncond');
            $this->filho($desc, 'vDescIncond', $this->valor($d['valores']['desconto']));
        }

        $trib = $this->filho($no, 'trib');
        $mun = $this->filho($trib, 'tribMun');
        $this->filho($mun, 'tribISSQN', (string) $d['iss']['trib_issqn']);
        $this->filho($mun, 'tpRetISSQN', $d['iss']['retido'] ? '2' : '1');
        if ($d['iss']['aliquota_obrigatoria'] && $d['iss']['aliquota'] !== null) {
            $this->filho($mun, 'pAliq', $this->valor($d['iss']['aliquota']));
        }

        $tot = $this->filho($trib, 'totTrib');
        match ($d['tot_trib']['tipo']) {
            'sn' => $this->filho($tot, 'pTotTribSN', $this->valor($d['tot_trib']['sn'])),
            'percentual' => (function () use ($tot, $d) {
                $p = $this->filho($tot, 'pTotTrib');
                $this->filho($p, 'pTotTribFed', $this->valor($d['tot_trib']['fed']));
                $this->filho($p, 'pTotTribEst', $this->valor($d['tot_trib']['est']));
                $this->filho($p, 'pTotTribMun', $this->valor($d['tot_trib']['mun']));
            })(),
            default => $this->filho($tot, 'indTotTrib', '0'),
        };
    }

    /**
     * Leiaute de produção (09/02/2026): finNFSe e cIndOp obrigatórios dentro do IBSCBS; CST e cClassTrib no gIBSCBS.
     * NT 009 (20/10/2026): finNFSe na raiz; cIndOp e gIBSCBS só quando o CST exige tributação (E0859, E0960, E0961).
     *
     * @param  array<string, mixed>  $ibs
     */
    private function montarIbsCbs(DOMElement $inf, array $ibs, string $leiaute): void
    {
        $no = $this->filho($inf, 'IBSCBS');
        $nt009 = $leiaute === self::LEIAUTE_NT009;

        if (! $nt009) {
            $this->filho($no, 'finNFSe', '0');
        }

        if ($ibs['ind_op'] !== '' && (! $nt009 || $ibs['exige_tributacao'])) {
            $this->filho($no, 'cIndOp', $ibs['ind_op']);
        }

        // 0 = o destinatário é o próprio tomador/adquirente: sem grupo dest (E0908).
        $this->filho($no, 'indDest', '0');

        $valores = $this->filho($no, 'valores');
        $trib = $this->filho($valores, 'trib');

        if ($nt009) {
            $this->filho($trib, 'CST', $ibs['cst']);
            $this->filho($trib, 'cClassTrib', $ibs['class_trib']);
            if ($ibs['exige_tributacao']) {
                $this->filho($trib, 'gIBSCBS');
            }

            return;
        }

        $grupo = $this->filho($trib, 'gIBSCBS');
        $this->filho($grupo, 'CST', $ibs['cst']);
        $this->filho($grupo, 'cClassTrib', $ibs['class_trib']);
    }

    private function filho(DOMElement $pai, string $nome, ?string $valor = null): DOMElement
    {
        $dom = $pai->ownerDocument;
        $no = $dom->createElementNS(self::NAMESPACE, $nome);
        if ($valor !== null) {
            $no->appendChild($dom->createTextNode($valor));
        }
        $pai->appendChild($no);

        return $no;
    }

    private function valor(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
