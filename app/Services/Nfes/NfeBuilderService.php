<?php

namespace App\Services\Nfes;

use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\Produtos\Produto;
use App\Models\Vendas\Venda;
use App\Repositories\Nfes\NfeRepository;
use Carbon\Carbon;
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

    public const CSOSN_SEM_CALCULO = ['102', '103', '300', '400'];

    public const CST_SEM_INCIDENCIA = ['04', '05', '06', '07', '08', '09'];

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
            throw new RuntimeException('Regime Normal ainda não é emitido. Use Simples Nacional ou aguarde a revisão contábil do regime.');
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
        $cliente = $venda->cliente;
        $cnpjDestino = $cliente ? $this->somenteDigitos($cliente->cnpj) : '';
        $cUf = $this->codigoUf((string) $this->empresa->uf);
        $cNf = $this->codigoNumerico($numero);

        $make->taginfNFe($this->dados(['versao' => '4.00']));
        $indFinal = $venda->ind_final;
        if ($indFinal === null) {
            $indFinal = strlen($cnpjDestino) === 14 ? 0 : 1;
        }

        $make->tagide($this->dados([
            'cUF' => $cUf,
            'cNF' => $cNf,
            'natOp' => $this->limitar($venda->natureza_operacao ?: 'VENDA DE MERCADORIA', 60),
            'mod' => 55,
            'serie' => (int) ($this->empresa->nfe_serie ?: 1),
            'nNF' => (int) $numero,
            'dhEmi' => Carbon::now($this->fuso($cUf))->format('Y-m-d\TH:i:sP'),
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
        ]));
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

        if ($cliente === null || $this->ehConsumidorSemDocumento($cliente)) {
            $make->tagdest($this->dados([
                'CPF' => '00000000000',
                'xNome' => 'CONSUMIDOR FINAL',
                'indIEDest' => 9,
            ]));

            if ($cliente !== null) {
                $this->montarEnderecoDestinatario($make, $cliente);
            }

            return;
        }

        $cnpj = $this->somenteDigitos($cliente->cnpj);
        $cpf = $this->somenteDigitos($cliente->cpf);
        $ie = strtoupper(trim((string) $cliente->inscricao_estadual));
        $temIe = $ie !== '' && $ie !== 'ISENTO';

        $dados = [
            'xNome' => $this->limitar($cliente->nome, 60),
            'indIEDest' => strlen($cnpj) === 14 && $temIe ? 1 : 9,
            'IE' => $temIe ? $this->somenteDigitos($ie) : null,
        ];

        if (strlen($cnpj) === 14) {
            $dados['CNPJ'] = $cnpj;
        } elseif (strlen($cpf) === 11) {
            $dados['CPF'] = $cpf;
            $dados['indIEDest'] = 9;
        } else {
            $dados['CPF'] = '00000000000';
            $dados['xNome'] = 'CONSUMIDOR FINAL';
            $dados['indIEDest'] = 9;
        }

        $make->tagdest($this->dados($dados));
        $this->montarEnderecoDestinatario($make, $cliente);
    }

    protected function montarEnderecoDestinatario(Make $make, Cliente $cliente): void
    {
        $logradouro = trim((string) $cliente->logradouro);
        $numero = trim((string) $cliente->numero);
        $bairro = trim((string) $cliente->bairro);
        $cidade = trim((string) $cliente->cidade);

        if ($logradouro === '' || $numero === '' || $bairro === '' || $cidade === '') {
            return;
        }

        $ufCliente = strtoupper(trim((string) $cliente->estado));
        $ufEmpresa = strtoupper((string) $this->empresa->uf);

        if ($ufCliente !== '' && $ufCliente !== $ufEmpresa) {
            throw new RuntimeException('O cliente é de outra UF. A emissão desta versão cobre apenas operação interna.');
        }

        if (mb_strtolower($cidade) !== mb_strtolower(trim((string) $this->empresa->cidade))) {
            throw new RuntimeException('O cliente está em outro município e o cadastro não tem o código IBGE dele. Use um cliente da mesma cidade da empresa ou deixe o endereço em branco.');
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
            $make->tagPIS($this->dados([
                'item' => $nItem,
                'CST' => $cstPis,
            ]));
            $make->tagCOFINS($this->dados([
                'item' => $nItem,
                'CST' => $cstCofins,
            ]));
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
            throw new RuntimeException('O CSOSN '.$csosn.' exige crédito ou substituição tributária. Esta emissão aceita 102, 103, 300 ou 400.');
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

        if (! in_array($cst, self::CST_SEM_INCIDENCIA, true)) {
            throw new RuntimeException('O CST de '.$imposto.' informado exige base de cálculo. Esta emissão aceita apenas os CST 04 a 09.');
        }

        return $cst;
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

    protected function ehConsumidorSemDocumento(?Cliente $cliente): bool
    {
        if ($cliente === null) {
            return true;
        }

        return $this->somenteDigitos($cliente->cnpj) === ''
            && $this->somenteDigitos($cliente->cpf) === '';
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
