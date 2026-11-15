<?php

namespace App\Services\Nfes;

use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\Produtos\Produto;
use App\Models\Vendas\Venda;
use App\Repositories\Nfes\NfeRepository;
use App\Services\Nfces\NfceBuilderService;
use NFePHP\Common\Certificate;
use NFePHP\Common\Keys;
use NFePHP\Common\TimeZoneByUF;
use NFePHP\Common\UFList;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use RuntimeException;
use stdClass;
use Throwable;

class NfeBuilderService
{
    protected const SCHEME = 'PL_010_V1.30';

    /**
     * CSOSN que o XML leva sem valores de ICMS. O 500 (ICMS-ST cobrado antes) vai sem o grupo do ST retido,
     * que é opcional no leiaute. 101 (crédito) e 201/202/203/900 (ST/cálculo) ainda não são emitidos.
     */
    public const CSOSN_SEM_CALCULO = ['102', '103', '300', '400', '500'];

    /** PIS/COFINS sem incidência (grupo PISNT/COFINSNT). */
    public const CST_SEM_INCIDENCIA = ['04', '05', '06', '07', '08', '09'];

    /**
     * PIS/COFINS no grupo "Outr" com base, alíquota e valor zerados. A SEFAZ-PB orienta o Simples Nacional
     * a informar CST 99 com alíquota 0% e valor 0,00 (o imposto é recolhido no DAS).
     */
    public const CST_OUTRAS_ZERADAS = ['49', '99'];

    public const CST_ACEITOS = [...self::CST_SEM_INCIDENCIA, ...self::CST_OUTRAS_ZERADAS];

    /** CFOP de venda de mercadoria com ICMS-ST já retido (contribuinte substituído; 5656/5667 são combustíveis). */
    public const CFOP_SUBSTITUIDO = ['5405', '5656', '5667'];

    public const MENSAGEM_NAO_CONTRIBUINTE = 'O destinatário não tem inscrição estadual (não contribuinte): marque "Consumidor final" como Sim. A SEFAZ rejeita essa combinação (código 696).';

    protected Tools $tools;

    protected NfeVinculoFiscal $vinculo;

    public function __construct(
        protected readonly Empresa $empresa,
    ) {
        $this->iniciarTools();
    }

    public function tools(): Tools
    {
        return $this->tools;
    }

    public static function criarTools(Empresa $empresa): Tools
    {
        return (new self($empresa))->tools();
    }

    public function buildAndSign(Venda $venda): string
    {
        $this->validarEmissaoInterna();
        $this->antesDeMontar();

        app(NfeRepository::class)->carregarParaMontagem($venda);
        $this->vinculo = NfeVinculoFiscal::daEmpresa($this->empresa)->usarVenda($venda);

        if ($venda->itens->isEmpty()) {
            throw new RuntimeException('A venda não tem itens para emitir a nota fiscal.');
        }

        $numero = $this->numeroNfe($venda);
        $make = new Make(self::SCHEME);

        $this->montarIdentificacao($make, $venda, $numero);
        $this->montarEmitente($make);
        $this->montarDestinatario($make, $venda);
        $totais = $this->montarItens($make, $venda);
        $this->montarTotais($make, $venda, $totais);
        $this->montarTransporte($make, $venda);
        $this->montarPagamento($make, $venda, $totais['vNF']);
        $this->montarInformacoes($make, $venda);

        $xml = $make->getXML();
        $erros = $make->getErrors();

        if ($xml === '' || $erros !== []) {
            throw new RuntimeException('Não foi possível montar a NF-e: '.implode(' ', $erros));
        }

        return $this->tools->signNFe($xml);
    }

    protected function iniciarTools(): void
    {
        $this->tools = new Tools($this->configuracaoJson(), $this->lerCertificado());
        $this->ajustarModelo();

        // Só os testes registram "nfe.soap" (SEFAZ simulada). Em uso real o Tools fala com a SEFAZ.
        if (app()->bound('nfe.soap')) {
            $this->tools->loadSoapClass(app('nfe.soap'));
        }
    }

    protected function ajustarModelo(): void {}

    protected function antesDeMontar(): void {}

    protected function validarEmissaoInterna(): void
    {
        if ((int) $this->empresa->nfe_destino !== 1) {
            throw new RuntimeException('A emissão desta versão cobre apenas operação interna. Ajuste o destino da operação na empresa para Interna.');
        }

        if ((int) $this->empresa->crt === 3) {
            throw new RuntimeException('Regime Normal ainda não é emitido. Use Simples Nacional ou MEI, que emitem com CSOSN sem destaque de imposto.');
        }

        // CSOSN só vale para CRT 1 e 4; com CRT 2 (excesso de sublimite) o ICMS vai por CST do regime normal (rejeição 591).
        if ((int) $this->empresa->crt === 2) {
            throw new RuntimeException('Simples Nacional com excesso de sublimite (CRT 2) informa o ICMS com CST do Regime Normal, que esta versão ainda não emite. A SEFAZ rejeitaria a nota com CSOSN (código 591).');
        }

        if ($this->somenteDigitos($this->empresa->cod_ibge) === '' || strlen($this->somenteDigitos($this->empresa->cod_ibge)) !== 7) {
            throw new RuntimeException('Informe o código IBGE do município na empresa antes de emitir nota fiscal.');
        }
    }

    protected function lerCertificado(): Certificate
    {
        $caminho = $this->empresa->certificado_path;
        $senha = $this->empresa->certificado_senha_decrypted;
        $arquivo = $caminho ? storage_path('app/'.$caminho) : '';

        if ($caminho === null || $caminho === '' || $senha === null || $senha === '' || ! is_file($arquivo)) {
            throw new RuntimeException('Certificado digital não configurado para esta empresa.');
        }

        try {
            return Certificate::readPfx((string) file_get_contents($arquivo), $senha);
        } catch (Throwable) {
            throw new RuntimeException('Certificado inválido ou senha incorreta.');
        }
    }

    protected function configuracaoJson(): string
    {
        $uf = strtoupper(trim((string) $this->empresa->uf));
        $cnpj = $this->somenteDigitos($this->empresa->cnpj);

        if (strlen($uf) !== 2 || strlen($cnpj) !== 14) {
            throw new RuntimeException('Informe a UF e o CNPJ da empresa antes de emitir nota fiscal.');
        }

        return json_encode([
            'atualizacao' => now()->format('Y-m-d H:i:s'),
            'tpAmb' => (int) ($this->empresa->nfe_ambiente ?: 2),
            'razaosocial' => (string) $this->empresa->razao_social,
            'siglaUF' => $uf,
            'cnpj' => $cnpj,
            'schemes' => self::SCHEME,
            'versao' => '4.00',
            'tokenIBPT' => '',
            'CSC' => (string) ($this->empresa->nfe_csc ?? ''),
            'CSCid' => (string) ($this->empresa->nfe_id_csc ?? ''),
        ], JSON_THROW_ON_ERROR);
    }

    protected function montarIdentificacao(Make $make, Venda $venda, string $numero): void
    {
        $cUf = $this->codigoUf((string) $this->empresa->uf);
        $cNf = $this->codigoNumerico($numero);

        $make->taginfNFe($this->dados(['versao' => '4.00']));
        $indIEDest = self::indIEDest($venda->cliente);
        $indFinal = $venda->ind_final;
        if ($indFinal === null) {
            // Só o contribuinte com IE sai como "não consumidor final" no automático.
            $indFinal = $indIEDest === 1 ? 0 : 1;
        } elseif ((int) $indFinal === 0 && $indIEDest === 9) {
            // Não contribuinte (indIEDest 9) é sempre consumidor final: a SEFAZ rejeita o contrário (696).
            throw new RuntimeException(self::MENSAGEM_NAO_CONTRIBUINTE);
        }

        $fuso = $this->fuso($cUf);
        $dhEmi = (new NfeDatas)->emissao($venda->data_venda, $fuso);
        $ide = [
            'cUF' => $cUf,
            'cNF' => $cNf,
            'natOp' => $this->limitar($venda->natureza_operacao ?: 'VENDA DE MERCADORIA', 60),
            'mod' => 55,
            'serie' => (int) ($this->empresa->nfe_serie ?: 1),
            'nNF' => (int) $numero,
            'dhEmi' => $dhEmi->format('Y-m-d\TH:i:sP'),
            'tpNF' => 1,
            'idDest' => 1,
            'cMunFG' => $this->somenteDigitos($this->empresa->cod_ibge),
            'tpImp' => 1,
            'tpEmis' => 1,
            'tpAmb' => (int) ($this->empresa->nfe_ambiente ?: 2),
            'finNFe' => 1,
            'indFinal' => (int) $indFinal,
            'indPres' => 1,
            'procEmi' => 0,
            'verProc' => '1.0.0',
        ];
        $saida = (new NfeDatas)->saida($venda->data_saida, $dhEmi);
        if ($saida !== null) {
            $ide['dhSaiEnt'] = $saida;
        }

        $make->tagide($this->dados($ide));
    }

    protected function montarEmitente(Make $make): void
    {
        $this->exigirEmitente();
        $ie = strtoupper(trim((string) $this->empresa->ie));

        $make->tagEmit($this->dados([
            'CNPJ' => $this->somenteDigitos($this->empresa->cnpj),
            'xNome' => $this->limitar($this->empresa->razao_social, 60),
            'xFant' => $this->limitar($this->empresa->nome_fantasia ?: $this->empresa->razao_social, 60),
            'IE' => $ie === 'ISENTO' ? 'ISENTO' : $this->somenteDigitos($ie),
            'CRT' => (int) ($this->empresa->crt ?: 1),
        ]));

        $make->tagenderEmit($this->dados([
            'xLgr' => $this->limitar($this->empresa->logradouro, 60),
            'nro' => $this->limitar($this->empresa->numero ?: 'S/N', 60),
            'xCpl' => $this->limitar($this->empresa->complemento, 60),
            'xBairro' => $this->limitar($this->empresa->bairro, 60),
            'cMun' => $this->somenteDigitos($this->empresa->cod_ibge),
            'xMun' => $this->limitar($this->empresa->cidade, 60),
            'UF' => strtoupper((string) $this->empresa->uf),
            'CEP' => $this->cepEmitente(),
            'cPais' => '1058',
            'xPais' => 'Brasil',
            'fone' => $this->somenteDigitos($this->empresa->telefone ?: $this->empresa->celular),
        ]));
    }

    protected function montarDestinatario(Make $make, Venda $venda): void
    {
        $cliente = $venda->cliente;

        // A NF-e (modelo 55) exige destinatário identificado: CPF zerado volta com rejeição 237.
        if (($mensagem = self::pendenciaDocumentoDestinatario($cliente)) !== null) {
            throw new RuntimeException($mensagem);
        }

        $cnpj = $this->somenteDigitos($cliente->cnpj);
        $cpf = $this->somenteDigitos($cliente->cpf);
        $indIEDest = self::indIEDest($cliente);

        // A tag IE só vai com indIEDest = 1; no 9 ela fica de fora.
        $dados = [
            'xNome' => $this->limitar($cliente->nome, 60),
            'indIEDest' => $indIEDest,
            'IE' => $indIEDest === 1 ? $this->somenteDigitos($cliente->inscricao_estadual) : null,
        ];

        if (strlen($cnpj) === 14) {
            $dados['CNPJ'] = $cnpj;
        } else {
            $dados['CPF'] = $cpf;
        }

        $make->tagdest($this->dados($dados));
        $this->montarEnderecoDestinatario($make, $cliente);
    }

    protected function montarEnderecoDestinatario(Make $make, Cliente $cliente): void
    {
        // A NF-e (modelo 55) sem enderDest volta com rejeição 726.
        if (($mensagem = self::pendenciaEnderecoDestinatario($cliente)) !== null) {
            throw new RuntimeException($mensagem);
        }

        $logradouro = trim((string) $cliente->logradouro);
        $numero = trim((string) $cliente->numero) ?: 'S/N';
        $bairro = trim((string) $cliente->bairro);
        $cidade = trim((string) $cliente->cidade);

        $ufCliente = strtoupper(trim((string) $cliente->estado));
        $ufEmpresa = strtoupper((string) $this->empresa->uf);

        if ($ufCliente !== '' && $ufCliente !== $ufEmpresa) {
            throw new RuntimeException('O cliente é de outra UF. A emissão desta versão cobre apenas operação interna.');
        }

        if (mb_strtolower($cidade) !== mb_strtolower(trim((string) $this->empresa->cidade))) {
            throw new RuntimeException('O cliente está em outro município e o cadastro não tem o código IBGE dele. Esta versão só emite NF-e para a cidade da empresa.');
        }

        $make->tagenderDest($this->dados([
            'xLgr' => $this->limitar($logradouro, 60),
            'nro' => $this->limitar($numero, 60),
            'xCpl' => $this->limitar($cliente->complemento, 60),
            'xBairro' => $this->limitar($bairro, 60),
            'cMun' => $this->somenteDigitos($this->empresa->cod_ibge),
            'xMun' => $this->limitar($cidade, 60),
            'UF' => $ufEmpresa,
            'CEP' => $this->cepDestinatario($cliente),
            'cPais' => '1058',
            'xPais' => 'Brasil',
            'fone' => $this->somenteDigitos($cliente->telefone ?: $cliente->celular),
        ]));
    }

    /**
     * @return array{vProd: float, vDesc: float, vOutro: float, vNF: float}
     */
    protected function montarItens(Make $make, Venda $venda): array
    {
        $itens = $venda->itens->values();
        $ultimo = $itens->count();
        $homologacao = (int) ($this->empresa->nfe_ambiente ?: 2) === 2;
        $descontos = $this->ratearDesconto((float) $venda->desconto, $itens);
        $somaProd = 0.0;
        $somaDesc = 0.0;

        foreach ($itens as $indice => $item) {
            $nItem = $indice + 1;
            $produto = $item->produto;
            $cfop = $this->cfopDoItem($item, $produto);
            $csosn = $this->csosnDoItem($item, $produto);
            if (($par = self::pendenciaCfopCsosn($cfop, $csosn)) !== null) {
                throw new RuntimeException('Item "'.$item->produto_nome.'": '.$par);
            }
            $cstPis = $this->cstResolvido($item, $produto, 'cst_pis', 'cst_pis', 'cst_pis', 'nfe_cst_pis_padrao', 'PIS');
            $cstCofins = $this->cstResolvido($item, $produto, 'cst_cofins', 'cst_cofins', 'cst_cofins', 'nfe_cst_cofins_padrao', 'COFINS');
            $unidade = $item->unidade ?: ($produto?->unidadeMedida?->sigla ?: 'UN');
            $ean = $this->eanDoItem($item);
            $descricao = $homologacao && $nItem === 1
                ? 'NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL'
                : $this->limitar($item->produto_nome, 120);

            $vProdItem = max(0, (float) $item->total + (float) $item->desconto);

            $prod = [
                'item' => $nItem,
                'cProd' => $this->limitar($item->produto_codigo ?: (string) $nItem, 60),
                'cEAN' => $ean,
                'xProd' => $descricao,
                'NCM' => $this->ncmDoItem($item, $produto),
                'CFOP' => $cfop,
                'uCom' => $this->limitar($unidade, 6),
                'qCom' => $this->quantidade($item->quantidade),
                'vUnCom' => $this->valor($item->preco_unitario),
                'vProd' => $this->valor($vProdItem),
                'cEANTrib' => $ean,
                'uTrib' => $this->limitar($unidade, 6),
                'qTrib' => $this->quantidade($item->quantidade),
                'vUnTrib' => $this->valor($item->preco_unitario),
                'indTot' => 1,
            ];

            if (! empty($item->cest)) {
                $prod['CEST'] = $this->somenteDigitos($item->cest);
            }

            $descItem = round((float) $item->desconto + (float) ($descontos[$indice] ?? 0), 2);
            if ($descItem > 0) {
                $prod['vDesc'] = $this->valor($descItem);
            }

            $somaProd = round($somaProd + (float) $this->valor($vProdItem), 2);
            $somaDesc = round($somaDesc + $descItem, 2);

            if ($nItem === $ultimo && (float) $venda->acrescimo > 0) {
                $prod['vOutro'] = $this->valor($venda->acrescimo);
            }

            $make->tagprod($this->dados($prod));
            $make->tagimposto($this->dados([
                'item' => $nItem,
                'vTotTrib' => '0.00',
            ]));
            $make->tagICMSSN($this->dados([
                'item' => $nItem,
                'orig' => $this->origemDoItem($item, $produto),
                'CSOSN' => $csosn,
            ]));
            $make->tagPIS($this->dados($this->grupoPisCofins($nItem, $cstPis, 'PIS')));
            $make->tagCOFINS($this->dados($this->grupoPisCofins($nItem, $cstCofins, 'COFINS')));
        }

        $vOutro = round((float) $venda->acrescimo, 2);

        return [
            'vProd' => $somaProd,
            'vDesc' => $somaDesc,
            'vOutro' => $vOutro,
            'vNF' => round($somaProd - $somaDesc + $vOutro, 2),
        ];
    }

    /**
     * @param  array{vProd: float, vDesc: float, vOutro: float, vNF: float}  $totais
     */
    protected function montarTotais(Make $make, Venda $venda, array $totais): void
    {
        $make->tagICMSTot($this->dados([
            'vBC' => '0.00',
            'vICMS' => '0.00',
            'vICMSDeson' => '0.00',
            'vFCP' => '0.00',
            'vBCST' => '0.00',
            'vST' => '0.00',
            'vFCPST' => '0.00',
            'vFCPSTRet' => '0.00',
            'vProd' => $this->valor($totais['vProd']),
            'vFrete' => '0.00',
            'vSeg' => '0.00',
            'vDesc' => $this->valor($totais['vDesc']),
            'vII' => '0.00',
            'vIPI' => '0.00',
            'vIPIDevol' => '0.00',
            'vPIS' => '0.00',
            'vCOFINS' => '0.00',
            'vOutro' => $this->valor($totais['vOutro']),
            'vNF' => $this->valor($totais['vNF']),
            'vTotTrib' => '0.00',
        ]));
    }

    protected function montarTransporte(Make $make, Venda $venda): void
    {
        $make->tagtransp($this->dados([
            'modFrete' => self::modFrete($venda->mod_frete),
        ]));
    }

    protected function montarPagamento(Make $make, Venda $venda, float $vNF): void
    {
        $tPag = self::tPag((string) $venda->forma_pagamento);

        $make->tagpag($this->dados([]));
        $make->tagdetPag($this->dados([
            'tPag' => $tPag,
            'xPag' => $tPag === '99' ? $this->limitar($venda->forma_pagamento ?: 'Outros', 60) : null,
            'vPag' => $this->valor($vNF),
        ]));
    }

    protected function montarInformacoes(Make $make, Venda $venda): void
    {
        $texto = trim((string) $venda->observacoes);
        $fisco = trim((string) $venda->inf_ad_fisco);

        if ((int) $this->empresa->crt === 4) {
            $aviso = 'Documento emitido por Microempreendedor Individual (MEI).';
            $texto = trim($aviso.($texto !== '' ? ' '.$texto : ''));
        }

        if ($texto === '' && $fisco === '') {
            return;
        }

        $make->taginfAdic($this->dados([
            'infCpl' => $this->limitar($texto, 5000),
            'infAdFisco' => $this->limitar($fisco, 2000),
        ]));
    }

    protected function numeroNfe(Venda $venda): string
    {
        $numero = $this->somenteDigitos($venda->nfe_numero ?? null);

        if ($numero === '' || (int) $numero < 1) {
            throw new RuntimeException('A venda não possui número de NF-e reservado.');
        }

        return str_pad($numero, 9, '0', STR_PAD_LEFT);
    }

    protected function codigoNumerico(string $numero): string
    {
        return Keys::random($numero);
    }

    protected function ratearDesconto(float $desconto, $itens): array
    {
        $restante = round($desconto, 2);
        $mapa = [];

        if ($restante <= 0) {
            return $mapa;
        }

        for ($indice = $itens->count() - 1; $indice >= 0 && $restante > 0; $indice--) {
            $disponivel = round((float) $itens[$indice]->total, 2);
            $usar = min($disponivel, $restante);

            if ($usar > 0) {
                $mapa[$indice] = $usar;
                $restante = round($restante - $usar, 2);
            }
        }

        if ($restante > 0) {
            throw new RuntimeException('O desconto da venda é maior que o valor dos itens.');
        }

        return $mapa;
    }

    protected function codigoUf(string $uf): int
    {
        try {
            return UFList::getCodeByUF(strtoupper(trim($uf)));
        } catch (Throwable) {
            throw new RuntimeException('A UF da empresa é inválida para a nota fiscal.');
        }
    }

    protected function fuso(int $cUf): string
    {
        return TimeZoneByUF::get($cUf);
    }

    protected function cfopDoItem($item, ?Produto $produto): string
    {
        $cfop = $this->somenteDigitos($this->vinculo->cfop($item, $produto)['valor']);

        if (strlen($cfop) !== 4 || ! str_starts_with($cfop, '5')) {
            throw new RuntimeException('Para operação interna o CFOP precisa ser da série 5. Ajuste o item, o produto ou o CFOP padrão da empresa.');
        }

        return $cfop;
    }

    protected function csosnDoItem($item, ?Produto $produto): string
    {
        $csosn = $this->somenteDigitos($this->vinculo->csosn($item, $produto)['valor']);

        if (! in_array($csosn, self::CSOSN_SEM_CALCULO, true)) {
            throw new RuntimeException('O CSOSN '.$csosn.' exige crédito ou cálculo de substituição tributária. Esta emissão aceita 102, 103, 300, 400 ou 500.');
        }

        return $csosn;
    }

    protected function eanDoItem($item): string
    {
        $ean = $this->somenteDigitos($item->ean ?? null);

        if ($ean === '' || $ean === '0') {
            return 'SEM GTIN';
        }

        if (! NfeVinculoFiscal::gtinValido($ean)) {
            throw new RuntimeException('O código de barras do item "'.$item->produto_nome.'" é inválido. Corrija o EAN ou deixe o campo em branco.');
        }

        return $ean;
    }

    protected function cstResolvido($item, ?Produto $produto, string $campoItem, string $campoProduto, string $campoPerfil, string $campoEmpresa, string $imposto): string
    {
        $cst = $this->vinculo->cst($item, $produto, $campoItem, $campoProduto, $campoPerfil, $campoEmpresa)['valor'];

        if (! in_array($cst, self::CST_ACEITOS, true)) {
            throw new RuntimeException('O CST de '.$imposto.' informado exige base de cálculo. Esta emissão aceita os CST 04 a 09, 49 ou 99 (sem valor de imposto).');
        }

        return $cst;
    }

    /**
     * CST 04 a 09 vão só com o código (PISNT/COFINSNT). CST 49 e 99 vão no grupo "Outr" com base,
     * alíquota e valor zerados, como a SEFAZ-PB orienta para o Simples Nacional.
     *
     * @return array<string, mixed>
     */
    protected function grupoPisCofins(int $nItem, string $cst, string $imposto): array
    {
        $grupo = ['item' => $nItem, 'CST' => $cst];

        if (in_array($cst, self::CST_OUTRAS_ZERADAS, true)) {
            $grupo['vBC'] = '0.00';
            $grupo['p'.$imposto] = '0.0000';
            $grupo['v'.$imposto] = '0.00';
        }

        return $grupo;
    }

    protected function ncmDoItem($item, ?Produto $produto): string
    {
        $ncm = $this->somenteDigitos($item->ncm ?: $produto?->ncm);

        if (strlen($ncm) !== 8) {
            throw new RuntimeException('Informe o NCM com 8 dígitos em todos os itens da nota.');
        }

        return $ncm;
    }

    protected function cepEmitente(): string
    {
        $cep = $this->somenteDigitos($this->empresa->cep);

        if (strlen($cep) !== 8) {
            throw new RuntimeException('Informe o CEP da empresa com 8 dígitos antes de emitir nota fiscal.');
        }

        return $cep;
    }

    protected function cepDestinatario(Cliente $cliente): string
    {
        $cep = $this->somenteDigitos($cliente->cep ?: $this->empresa->cep);

        if (strlen($cep) !== 8) {
            throw new RuntimeException('O CEP do destinatário precisa ter 8 dígitos.');
        }

        return $cep;
    }

    protected function origemDoItem($item, ?Produto $produto): string
    {
        $origem = $this->somenteDigitos($item->origem ?? $produto?->origem);

        return $origem === '' ? '0' : substr($origem, 0, 1);
    }

    public static function modFrete(mixed $valor): int
    {
        $mod = $valor === null || $valor === '' ? 9 : (int) $valor;

        return in_array($mod, [0, 1, 2, 3, 4, 9], true) ? $mod : 9;
    }

    public static function tPag(string $forma): string
    {
        $nome = mb_strtolower(trim($forma));

        return match ($nome) {
            'dinheiro' => '01',
            'cheque' => '02',
            'cartão de crédito', 'cartao de credito' => '03',
            'cartão de débito', 'cartao de debito' => '04',
            'pix' => '17',
            default => '99',
        };
    }

    /**
     * Documento do destinatário que a SEFAZ recusaria na NF-e (modelo 55). null quando está ok.
     * Vale o CNPJ quando os dois estão preenchidos, como em montarDestinatario().
     */
    public static function pendenciaDocumentoDestinatario(?Cliente $cliente): ?string
    {
        $cnpj = preg_replace('/\D/', '', (string) $cliente?->cnpj) ?? '';
        $cpf = preg_replace('/\D/', '', (string) $cliente?->cpf) ?? '';

        if ($cliente === null || ($cnpj === '' && $cpf === '')) {
            return 'A NF-e exige destinatário com CPF ou CNPJ (sem documento a SEFAZ rejeita com o código 237). Selecione um cliente com documento ou emita NFC-e para consumidor não identificado.';
        }

        $documento = $cnpj !== '' ? $cnpj : $cpf;
        $tipo = $cnpj !== '' ? 'CNPJ' : 'CPF';

        if (strlen($documento) !== ($tipo === 'CNPJ' ? 14 : 11) || ! NfceBuilderService::documentoValido($documento)) {
            return 'O '.$tipo.' do cliente "'.$cliente->nome.'" é inválido. Corrija o cadastro do cliente antes de emitir a NF-e.';
        }

        return null;
    }

    /** Endereço mínimo do destinatário da NF-e. Número em branco sai como "S/N". null quando está ok. */
    public static function pendenciaEnderecoDestinatario(?Cliente $cliente): ?string
    {
        $faltando = [];
        foreach (['logradouro' => 'logradouro', 'bairro' => 'bairro', 'cidade' => 'cidade'] as $campo => $rotulo) {
            if (trim((string) $cliente?->{$campo}) === '') {
                $faltando[] = $rotulo;
            }
        }

        if ($faltando === []) {
            return null;
        }

        return 'Complete o endereço do cliente "'.($cliente->nome ?? '').'" ('.implode(', ', $faltando).'): a SEFAZ rejeita NF-e sem endereço do destinatário (código 726).';
    }

    /** Contribuinte com IE (indIEDest = 1). */
    public static function destinatarioContribuinte(?Cliente $cliente): bool
    {
        return self::indIEDest($cliente) === 1;
    }

    /**
     * Indicador da IE do destinatário da NF-e: 1 = CNPJ com IE; 9 = não contribuinte (CPF, CNPJ sem IE ou com IE "ISENTO").
     * O MOC prevê 2 para contribuinte isento, mas a UF do destinatário pode recusar: a SEFAZ-PB devolveu 805
     * ("não permite Contribuinte Isento") em homologação em 14/11/2026. O 9 ("pode ou não possuir IE") é aceito.
     * A NFC-e usa sempre 9 (NfceBuilderService).
     */
    public static function indIEDest(?Cliente $cliente): int
    {
        $cnpj = preg_replace('/\D/', '', (string) $cliente?->cnpj) ?? '';
        $ie = strtoupper(trim((string) $cliente?->inscricao_estadual));

        return strlen($cnpj) === 14 && $ie !== '' && $ie !== 'ISENTO' ? 1 : 9;
    }

    /**
     * CFOP e CSOSN precisam combinar (rejeição 386, vista na NFC-e em homologação): CSOSN 500 (ST já retido)
     * vende com CFOP de substituído (5405) e o CFOP 5405 só vale com CSOSN 500. null quando está ok.
     */
    public static function pendenciaCfopCsosn(string $cfop, string $csosn): ?string
    {
        $substituido = in_array($cfop, self::CFOP_SUBSTITUIDO, true);

        if ($csosn === '500' && ! $substituido) {
            return 'CSOSN 500 (ICMS-ST já retido) pede CFOP 5405, de mercadoria com substituição tributária. Com o CFOP '.$cfop.' a SEFAZ rejeita (código 386).';
        }

        if ($substituido && $csosn !== '500') {
            return 'O CFOP '.$cfop.' é de mercadoria com ICMS-ST já retido e pede CSOSN 500. Com o CSOSN '.$csosn.' a SEFAZ rejeita (código 386).';
        }

        return null;
    }

    protected function somenteDigitos(mixed $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }

    protected function valor(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    protected function quantidade(mixed $valor): string
    {
        return number_format((float) $valor, 4, '.', '');
    }

    protected function limitar(?string $texto, int $tamanho): string
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return '';
        }

        return mb_substr($texto, 0, $tamanho);
    }

    protected function dados(array $valores): stdClass
    {
        return (object) $valores;
    }

    protected function exigirEmitente(): void
    {
        $ie = strtoupper(trim((string) $this->empresa->ie));
        if ($ie === '') {
            throw new RuntimeException('Informe a inscrição estadual da empresa antes de emitir a nota.');
        }

        $this->cepEmitente();

        foreach ([
            'logradouro' => 'o logradouro',
            'bairro' => 'o bairro',
            'cidade' => 'a cidade',
            'uf' => 'a UF',
        ] as $campo => $rotulo) {
            if (trim((string) $this->empresa->{$campo}) === '') {
                throw new RuntimeException('Informe '.$rotulo.' da empresa antes de emitir a nota.');
            }
        }
    }
}
