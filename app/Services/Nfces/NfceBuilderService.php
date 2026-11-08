<?php

namespace App\Services\Nfces;

use App\Models\Nfes\Nfe;
use App\Models\Vendas\Venda;
use App\Repositories\Nfces\NfceRepository;
use App\Services\Nfes\NfeBuilderService;
use App\Services\Nfes\NfeVinculoFiscal;
use Carbon\Carbon;
use NFePHP\NFe\Make;
use RuntimeException;

class NfceBuilderService extends NfeBuilderService
{
    private ?string $numeroInformado = null;

    private float $valorRecebido = 0;

    private ?string $documentoConsumidor = null;

    public function buildAndSignNfce(Venda $venda, string $numero, float $valorRecebido, ?string $documento): string
    {
        $this->numeroInformado = $numero;
        $this->valorRecebido = $valorRecebido;
        $this->documentoConsumidor = $documento;

        return $this->buildAndSign($venda);
    }

    public function validarCadastroNfce(array $itens): void
    {
        $this->validarEmissaoInterna();
        $this->exigirCscNfce();
        $this->exigirEmitente();
        $venda = new Venda(['natureza_operacao' => 'VENDA DE MERCADORIA']);
        $this->vinculo = NfeVinculoFiscal::daEmpresa($this->empresa)->usarVenda($venda);

        foreach ($itens as $item) {
            $obj = (object) $item;
            $produto = isset($item['produto_id'])
                ? app(NfceRepository::class)->produtoComPerfil((int) $item['produto_id'])
                : null;
            $this->ncmDoItem($obj, $produto);
            $this->eanDoItem($obj);
            $this->cfopDoItem($obj, $produto);
            $this->csosnDoItem($obj, $produto);
            $this->cstResolvido($obj, $produto, 'cst_pis', 'cst_pis', 'cst_pis', 'nfe_cst_pis_padrao', 'PIS');
            $this->cstResolvido($obj, $produto, 'cst_cofins', 'cst_cofins', 'cst_cofins', 'nfe_cst_cofins_padrao', 'COFINS');
        }
    }

    public function exigirCscNfce(): void
    {
        if ($this->cscDaConfiguracao() === '' || $this->idCscDaConfiguracao() === '') {
            $ambiente = (int) ($this->empresa->nfe_ambiente ?: 2) === 1 ? 'produção' : 'homologação';
            throw new RuntimeException('Informe o CSC e o idCSC de '.$ambiente.' na empresa antes de emitir a NFC-e.');
        }
    }

    public static function documentoValido(string $digitos): bool
    {
        if (preg_match('/^(\d)\1+$/', $digitos) === 1) {
            return false;
        }

        if (strlen($digitos) === 11) {
            return self::digitoConfere($digitos, 9) && self::digitoConfere($digitos, 10);
        }

        if (strlen($digitos) === 14) {
            return self::digitoConfere($digitos, 12) && self::digitoConfere($digitos, 13);
        }

        return false;
    }

    protected function ajustarModelo(): void
    {
        $this->tools->model(Nfe::MODELO_NFCE);
    }

    protected function antesDeMontar(): void
    {
        $this->exigirCscNfce();
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
            'CSC' => $this->cscDaConfiguracao(),
            'CSCid' => $this->idCscDaConfiguracao(),
        ], JSON_THROW_ON_ERROR);
    }

    protected function montarIdentificacao(Make $make, Venda $venda, string $numero): void
    {
        $cUf = $this->codigoUf((string) $this->empresa->uf);
        $cNf = $this->codigoNumerico($numero);

        $make->taginfNFe($this->dados(['versao' => '4.00']));
        $make->tagide($this->dados([
            'cUF' => $cUf,
            'cNF' => $cNf,
            'natOp' => $this->limitar($venda->natureza_operacao ?: 'VENDA DE MERCADORIA', 60),
            'mod' => 65,
            'serie' => (int) ($this->empresa->nfce_serie ?: 1),
            'nNF' => (int) $numero,
            'dhEmi' => Carbon::now($this->fuso($cUf))->format('Y-m-d\TH:i:sP'),
            'tpNF' => 1,
            'idDest' => 1,
            'cMunFG' => $this->somenteDigitos($this->empresa->cod_ibge),
            'tpImp' => 4,
            'tpEmis' => 1,
            'tpAmb' => (int) ($this->empresa->nfe_ambiente ?: 2),
            'finNFe' => 1,
            'indFinal' => 1,
            'indPres' => 1,
            'procEmi' => 0,
            'verProc' => '1.0.0',
        ]));
    }

    protected function montarDestinatario(Make $make, Venda $venda): void
    {
        $documento = $this->documentoDoConsumidor($venda);
        if ($documento === null) {
            return;
        }

        if (! self::documentoValido($documento)) {
            throw new RuntimeException('CPF ou CNPJ do consumidor é inválido.');
        }

        $dados = [
            'indIEDest' => 9,
        ];
        if (strlen($documento) === 14) {
            $dados['CNPJ'] = $documento;
        } else {
            $dados['CPF'] = $documento;
        }

        $nome = trim((string) ($venda->cliente->nome ?? ''));
        if ($nome !== '') {
            $dados['xNome'] = $this->limitar($nome, 60);
        }

        $make->tagdest($this->dados($dados));
    }

    protected function montarPagamento(Make $make, Venda $venda, float $vNF): void
    {
        $tPag = self::tPag((string) $venda->forma_pagamento);
        $vNF = round($vNF, 2);
        $recebido = round($this->valorRecebido, 2);
        $troco = 0.0;
        $vPag = $vNF;

        if ($tPag === '01' && $recebido > $vNF) {
            $troco = round($recebido - $vNF, 2);
            $vPag = $recebido;
        }

        $pag = [];
        if ($troco > 0) {
            $pag['vTroco'] = $this->valor($troco);
        }

        $make->tagpag($this->dados($pag));
        $make->tagdetPag($this->dados([
            'tPag' => $tPag,
            'xPag' => $tPag === '99' ? $this->limitar($venda->forma_pagamento ?: 'Outros', 60) : null,
            'vPag' => $this->valor($vPag),
            // Cartão na NFC-e exige o grupo card (senão, rejeição 391). Sem TEF integrado ao sistema: tpIntegra 2 (maquininha).
            'tpIntegra' => in_array($tPag, ['03', '04'], true) ? 2 : null,
        ]));
    }

    protected function numeroNfe(Venda $venda): string
    {
        $numero = $this->somenteDigitos($this->numeroInformado);

        if ($numero === '' || (int) $numero < 1) {
            throw new RuntimeException('A venda não possui número de NFC-e reservado.');
        }

        return str_pad($numero, 9, '0', STR_PAD_LEFT);
    }

    private function cscDaConfiguracao(): string
    {
        $producao = (int) ($this->empresa->nfe_ambiente ?: 2) === 1;
        $csc = $producao
            ? $this->empresa->nfce_csc_producao_decrypted
            : $this->empresa->nfce_csc_homologacao_decrypted;

        return (string) ($csc ?? '');
    }

    private function idCscDaConfiguracao(): string
    {
        $producao = (int) ($this->empresa->nfe_ambiente ?: 2) === 1;

        return (string) ($producao
            ? $this->empresa->nfce_id_csc_producao
            : $this->empresa->nfce_id_csc_homologacao);
    }

    private function documentoDoConsumidor(Venda $venda): ?string
    {
        $informado = $this->somenteDigitos($this->documentoConsumidor);
        if ($informado !== '') {
            return $informado;
        }

        $cliente = $venda->cliente;
        if ($cliente === null) {
            return null;
        }

        $cnpj = $this->somenteDigitos($cliente->cnpj);
        if (strlen($cnpj) === 14) {
            return $cnpj;
        }

        $cpf = $this->somenteDigitos($cliente->cpf);
        if (strlen($cpf) === 11) {
            return $cpf;
        }

        return null;
    }

    private static function digitoConfere(string $digitos, int $posicao): bool
    {
        $soma = 0;
        $peso = strlen($digitos) === 11 ? $posicao + 1 : ($posicao === 12 ? 5 : 6);

        for ($i = 0; $i < $posicao; $i++) {
            $soma += (int) $digitos[$i] * $peso;
            $peso--;
            if (strlen($digitos) === 14 && $peso < 2) {
                $peso = 9;
            }
        }

        $resto = $soma % 11;
        $esperado = $resto < 2 ? 0 : 11 - $resto;

        return $esperado === (int) $digitos[$posicao];
    }
}
