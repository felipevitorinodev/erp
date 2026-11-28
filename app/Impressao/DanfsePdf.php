<?php

namespace App\Impressao;

use App\Models\Fiscal\Municipio;
use App\Models\Nfses\Nfse;
use App\Services\Nfses\NfseCodigos;
use App\Services\Nfses\NfseRegras;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Com\Tecnick\Barcode\Barcode;
use DOMDocument;
use DOMXPath;
use RuntimeException;
use Throwable;

/**
 * DANFSe v2.0 (NT 008/2026): gerado aqui a partir do XML da NFS-e, porque a API de DANFSe do ADN foi desativada.
 * Só imprime o que está no XML (item 2.1). Página única A4, retrato.
 */
class DanfsePdf
{
    public function render(Nfse $nfse): string
    {
        $dados = $this->dados((string) $nfse->xml_nfse);
        $dados['cancelada'] = $nfse->situacao === 'cancelada';
        $dados['qrcode'] = $this->qrcode((string) config('nfse.consulta_publica').$dados['chave']);

        return Pdf::loadView('pdf.danfse', ['d' => $dados])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(string $xml): array
    {
        $dom = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        try {
            if ($xml === '' || ! $dom->loadXML($xml, LIBXML_NONET)) {
                throw new RuntimeException('XML da NFS-e ilegível.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }

        $xp = new DOMXPath($dom);
        $v = function (string $caminho, ?\DOMNode $contexto = null) use ($xp): string {
            $partes = array_map(fn ($p) => "*[local-name()='{$p}']", explode('/', $caminho));
            $no = $xp->query(($contexto ? '' : '//').implode('/', $partes), $contexto)?->item(0);

            return $no ? trim($no->textContent) : '';
        };
        $no = function (string $caminho) use ($xp): ?\DOMNode {
            $partes = array_map(fn ($p) => "*[local-name()='{$p}']", explode('/', $caminho));

            return $xp->query('//'.implode('/', $partes))?->item(0);
        };

        $inf = $no('infNFSe');
        $chave = preg_match('/(\d{6}[0-9A-Z]{14}\d{30})$/', (string) $inf?->attributes?->getNamedItem('Id')?->nodeValue, $m) ? $m[1] : '';
        $dps = $no('DPS/infDPS');
        $toma = $dps ? $xp->query("*[local-name()='toma']", $dps)?->item(0) : null;
        $emit = $no('infNFSe/emit');

        $tpAmb = $v('DPS/infDPS/tpAmb');
        $opSimpNac = (int) $v('DPS/infDPS/prest/regTrib/opSimpNac');
        $regAp = (int) $v('DPS/infDPS/prest/regTrib/regApTribSN');
        $regEsp = (int) $v('DPS/infDPS/prest/regTrib/regEspTrib');
        $tribIssqn = $v('DPS/infDPS/valores/trib/tribMun/tribISSQN');
        $tpRet = $v('DPS/infDPS/valores/trib/tribMun/tpRetISSQN');

        $vServ = (float) $v('DPS/infDPS/valores/vServPrest/vServ');
        $vDescIncond = (float) $v('DPS/infDPS/valores/vDescCondIncond/vDescIncond');
        $vDescCond = (float) $v('DPS/infDPS/valores/vDescCondIncond/vDescCond');
        $vLiq = $v('infNFSe/valores/vLiq');
        $vTotalRet = (float) $v('infNFSe/valores/vTotalRet');
        $vIbs = $v('infNFSe/IBSCBS/totCIBS/gIBS/vIBSTot');
        $vCbs = $v('infNFSe/IBSCBS/totCIBS/gCBS/vCBS');
        $vTotNf = $v('infNFSe/IBSCBS/totCIBS/vTotNF');

        $pessoa = function (?\DOMNode $raiz) use ($v): ?array {
            if (! $raiz) {
                return null;
            }

            $doc = $v('CNPJ', $raiz) ?: $v('CPF', $raiz) ?: $v('NIF', $raiz);
            $enderecoNac = $v('end/endNac/cMun', $raiz) !== '' ? 'end/endNac' : 'enderNac';

            return [
                'documento' => $this->documento($doc),
                'im' => $v('IM', $raiz),
                'nome' => $v('xNome', $raiz),
                'fone' => $v('fone', $raiz),
                'email' => $v('email', $raiz),
                'logradouro' => trim(implode(', ', array_filter([
                    $v('end/xLgr', $raiz) ?: $v('enderNac/xLgr', $raiz),
                    $v('end/nro', $raiz) ?: $v('enderNac/nro', $raiz),
                    $v('end/xCpl', $raiz) ?: $v('enderNac/xCpl', $raiz),
                    $v('end/xBairro', $raiz) ?: $v('enderNac/xBairro', $raiz),
                ]))),
                'cod_ibge' => $v($enderecoNac.'/cMun', $raiz),
                'municipio' => $this->municipio($v($enderecoNac.'/cMun', $raiz)),
                'cep' => $this->cep($v($enderecoNac.'/CEP', $raiz)),
            ];
        };

        $prestador = $pessoa($emit);
        $tomador = $pessoa($toma);

        return [
            'chave' => $chave,
            'homologacao' => $tpAmb === '2',
            'ambiente_texto' => $tpAmb === '1' ? 'Produção' : 'Produção restrita',
            'ambiente_gerador' => $v('infNFSe/ambGer') === '1' ? 'Prefeitura' : 'Sistema Nacional NFS-e',
            'local_emissao' => $v('infNFSe/xLocEmi'),
            'numero' => $v('infNFSe/nNFSe'),
            'competencia' => $this->data($v('DPS/infDPS/dCompet')),
            'emissao_nfse' => $this->dataHora($v('infNFSe/dhProc')),
            'numero_dps' => $v('DPS/infDPS/nDPS'),
            'serie_dps' => $v('DPS/infDPS/serie'),
            'emissao_dps' => $this->dataHora($v('DPS/infDPS/dhEmi')),
            'emitente' => match ($v('DPS/infDPS/tpEmit')) {
                '2' => 'Tomador do serviço',
                '3' => 'Intermediário do serviço',
                default => 'Prestador do serviço',
            },
            'finalidade' => 'NFS-e regular',
            'prestador' => $prestador,
            'simples' => match ($opSimpNac) {
                1 => 'Não optante',
                2 => 'Optante - Microempreendedor Individual (MEI)',
                3 => 'Optante - Microempresa ou Empresa de Pequeno Porte (ME/EPP)',
                default => '-',
            },
            'regime_apuracao' => $regAp ? (NfseRegras::REGIMES_APURACAO_SN[$regAp] ?? '-') : '-',
            'tomador' => $tomador,
            'servico' => [
                'c_trib_nac' => NfseCodigos::formatarServico($v('DPS/infDPS/serv/cServ/cTribNac')),
                'c_trib_mun' => $v('DPS/infDPS/serv/cServ/cTribMun'),
                'nbs' => NfseCodigos::formatarNbs($v('DPS/infDPS/serv/cServ/cNBS')),
                'local' => $v('infNFSe/xLocPrestacao'),
                'x_trib_nac' => $v('infNFSe/xTribNac'),
                'x_trib_mun' => $v('infNFSe/xTribMun'),
                'x_nbs' => $v('infNFSe/xNBS'),
                'descricao' => $v('DPS/infDPS/serv/cServ/xDescServ'),
            ],
            'iss' => [
                'tributacao' => match ($tribIssqn) {
                    '1' => 'Operação tributável',
                    '2' => 'Imunidade',
                    '3' => 'Exportação de serviço',
                    '4' => 'Não incidência',
                    default => '-',
                },
                'incidencia' => $v('infNFSe/xLocIncid') ?: '-',
                'regime_especial' => NfseRegras::REGIMES_ESPECIAIS[$regEsp] ?? '-',
                'desconto_incondicionado' => $vDescIncond,
                'base' => $v('infNFSe/valores/vBC'),
                'aliquota' => $v('infNFSe/valores/pAliqAplic') ?: $v('DPS/infDPS/valores/trib/tribMun/pAliq'),
                'retencao' => match ($tpRet) {
                    '2' => 'Retido pelo tomador',
                    '3' => 'Retido pelo intermediário',
                    default => 'Não retido',
                },
                'valor' => $v('infNFSe/valores/vISSQN'),
                'sujeito' => $tribIssqn === '1',
            ],
            'ibscbs' => [
                'cst' => $v('DPS/infDPS/IBSCBS/valores/trib/CST') ?: $v('DPS/infDPS/IBSCBS/valores/trib/gIBSCBS/CST'),
                'class_trib' => $v('DPS/infDPS/IBSCBS/valores/trib/cClassTrib') ?: $v('DPS/infDPS/IBSCBS/valores/trib/gIBSCBS/cClassTrib'),
                'ind_op' => $v('DPS/infDPS/IBSCBS/cIndOp'),
                'incidencia' => $v('infNFSe/IBSCBS/xLocalidadeIncid') ?: '-',
                'base' => $v('infNFSe/IBSCBS/valores/vBC'),
                'p_ibs_uf' => $v('infNFSe/IBSCBS/valores/uf/pIBSUF'),
                'p_ibs_mun' => $v('infNFSe/IBSCBS/valores/mun/pIBSMun'),
                'p_ibs_uf_efetiva' => $v('infNFSe/IBSCBS/valores/uf/pAliqEfetUF'),
                'p_ibs_mun_efetiva' => $v('infNFSe/IBSCBS/valores/mun/pAliqEfetMun'),
                'v_ibs_uf' => $v('infNFSe/IBSCBS/totCIBS/gIBS/gIBSUFTot/vIBSUF'),
                'v_ibs_mun' => $v('infNFSe/IBSCBS/totCIBS/gIBS/gIBSMunTot/vIBSMun'),
                'v_ibs' => $vIbs,
                'p_cbs' => $v('infNFSe/IBSCBS/valores/fed/pCBS'),
                'p_cbs_efetiva' => $v('infNFSe/IBSCBS/valores/fed/pAliqEfetCBS'),
                'v_cbs' => $vCbs,
            ],
            'federal' => [
                'irrf' => $v('DPS/infDPS/valores/trib/tribFed/vRetIRRF'),
                'cp' => $v('DPS/infDPS/valores/trib/tribFed/vRetCP'),
                'csll' => $v('DPS/infDPS/valores/trib/tribFed/vRetCSLL'),
                'pis' => $v('DPS/infDPS/valores/trib/tribFed/piscofins/vPis'),
                'cofins' => $v('DPS/infDPS/valores/trib/tribFed/piscofins/vCofins'),
            ],
            'totais' => [
                'servico' => $vServ,
                'desconto_incondicionado' => $vDescIncond,
                'desconto_condicionado' => $vDescCond,
                'retencoes' => $vTotalRet,
                'liquido' => $vLiq,
                'ibscbs' => $vIbs !== '' || $vCbs !== '' ? (float) $vIbs + (float) $vCbs : null,
                'liquido_ibscbs' => $vTotNf,
            ],
            'tributos_aproximados' => $this->tributosAproximados($v),
            'informacoes' => $v('infNFSe/xOutInf') ?: $v('DPS/infDPS/serv/infoCompl/xInfComp'),
        ];
    }

    private function tributosAproximados(callable $v): string
    {
        $base = 'DPS/infDPS/valores/trib/totTrib/';

        if (($sn = $v($base.'pTotTribSN')) !== '') {
            return 'Simples Nacional '.$this->numero($sn).'%';
        }

        if (($fed = $v($base.'pTotTrib/pTotTribFed')) !== '') {
            return 'federais '.$this->numero($fed).'%, estaduais '
                .$this->numero($v($base.'pTotTrib/pTotTribEst')).'%, municipais '.$this->numero($v($base.'pTotTrib/pTotTribMun')).'%';
        }

        if (($fed = $v($base.'vTotTrib/vTotTribFed')) !== '') {
            return 'federais R$ '.$this->numero($fed).', estaduais R$ '
                .$this->numero($v($base.'vTotTrib/vTotTribEst')).', municipais R$ '.$this->numero($v($base.'vTotTrib/vTotTribMun'));
        }

        return '';
    }

    private function qrcode(string $conteudo): string
    {
        try {
            $png = (new Barcode)
                ->getBarcodeObj('QRCODE,M', $conteudo, -4, -4, 'black', [-2, -2, -2, -2])
                ->setBackgroundColor('white')
                ->getPngData();

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (Throwable) {
            return '';
        }
    }

    /** Nome e UF do município pelo código IBGE que está no XML. */
    private function municipio(string $codigo): string
    {
        if (! preg_match('/^\d{7}$/', $codigo)) {
            return '-';
        }

        $municipio = Municipio::query()->where('codigo', $codigo)->first(['nome', 'uf']);

        return $municipio ? $municipio->nome.' / '.$municipio->uf : $codigo;
    }

    private function documento(string $digitos): string
    {
        return match (strlen($digitos)) {
            14 => substr($digitos, 0, 2).'.'.substr($digitos, 2, 3).'.'.substr($digitos, 5, 3).'/'.substr($digitos, 8, 4).'-'.substr($digitos, 12, 2),
            11 => substr($digitos, 0, 3).'.'.substr($digitos, 3, 3).'.'.substr($digitos, 6, 3).'-'.substr($digitos, 9, 2),
            default => $digitos,
        };
    }

    private function cep(string $cep): string
    {
        return strlen($cep) === 8 ? substr($cep, 0, 5).'-'.substr($cep, 5) : $cep;
    }

    private function data(string $valor): string
    {
        try {
            return $valor !== '' ? Carbon::parse($valor)->format('d/m/Y') : '-';
        } catch (Throwable) {
            return $valor;
        }
    }

    private function dataHora(string $valor): string
    {
        try {
            return $valor !== '' ? Carbon::parse($valor)->format('d/m/Y H:i:s') : '-';
        } catch (Throwable) {
            return $valor;
        }
    }

    private function numero(string $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }
}
