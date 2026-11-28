<?php

namespace App\Services\Nfes;

use App\Models\Empresas\Empresa;
use App\Models\Vendas\Venda;
use App\Services\Vendas\DivisaoFiscalVenda;

/**
 * Confere, antes de confirmar a venda e consumir número, os dados que o NfeBuilderService recusaria.
 * As regras precisam acompanhar as do builder (CFOP série 5, CSOSN/CST sem cálculo, endereço do destinatário).
 */
class NfePendenciasService
{
    /**
     * @return list<string>
     */
    public function verificar(Venda $venda, Empresa $empresa): array
    {
        $venda->loadMissing(['itens.produto.perfilFiscal', 'cliente']);
        $vinculo = NfeVinculoFiscal::daEmpresa($empresa)->usarVenda($venda);
        $pendencias = [];

        if (blank($venda->natureza_operacao)) {
            $pendencias[] = 'Informe a natureza da operação.';
        }

        array_push($pendencias, ...$this->pendenciasDeData($venda, $empresa));

        if (blank($venda->forma_pagamento)) {
            $pendencias[] = 'Selecione a forma de pagamento.';
        }

        if ($venda->itens->isEmpty()) {
            $pendencias[] = 'Adicione pelo menos um item.';
        }

        foreach ($venda->itens as $item) {
            $produto = $item->produto;
            $nome = '"'.$item->produto_nome.'"';

            if (DivisaoFiscalVenda::ehServico($item)) {
                $pendencias[] = "Item {$nome}: é um serviço e não entra na NF-e. Remova o item e emita a NFS-e para ele.";

                continue;
            }

            $ncm = $this->digitos($item->ncm ?: $produto?->ncm);
            if (strlen($ncm) !== 8) {
                $pendencias[] = "Item {$nome}: informe o NCM com 8 dígitos.";
            }

            $ean = $this->digitos($item->ean);
            if ($ean !== '' && $ean !== '0' && ! NfeVinculoFiscal::gtinValido($ean)) {
                $pendencias[] = "Item {$nome}: o código de barras é inválido. Corrija o EAN ou deixe em branco.";
            }

            $cfop = $this->digitos($vinculo->cfop($item, $produto)['valor']);
            if (strlen($cfop) !== 4 || ! str_starts_with($cfop, '5')) {
                $pendencias[] = "Item {$nome}: o CFOP precisa ter 4 dígitos e ser da série 5 (operação interna).";
            }

            $csosn = $this->digitos($vinculo->csosn($item, $produto)['valor']);
            if (! in_array($csosn, NfeBuilderService::CSOSN_SEM_CALCULO, true)) {
                $pendencias[] = "Item {$nome}: CSOSN {$csosn} não é aceito (use 102, 103, 300, 400 ou 500).";
            } elseif (($par = NfeBuilderService::pendenciaCfopCsosn($cfop, $csosn)) !== null) {
                $pendencias[] = "Item {$nome}: {$par}";
            }

            foreach (['cst_pis' => 'PIS', 'cst_cofins' => 'COFINS'] as $campo => $imposto) {
                $cst = $vinculo->cst($item, $produto, $campo, $campo, $campo, 'nfe_'.$campo.'_padrao')['valor'];
                if (! in_array($cst, NfeBuilderService::CST_ACEITOS, true)) {
                    $pendencias[] = "Item {$nome}: CST de {$imposto} {$cst} não é aceito (use 04 a 09, 49 ou 99).";
                }
            }

            if ((float) $item->quantidade <= 0) {
                $pendencias[] = "Item {$nome}: quantidade inválida.";
            }
        }

        if ((float) $venda->desconto > (float) $venda->itens->sum('total')) {
            $pendencias[] = 'O desconto é maior que o valor dos itens.';
        }

        array_push($pendencias, ...$this->pendenciasDeDestinatario($venda));

        $cliente = $venda->cliente;
        if ($cliente && NfeBuilderService::pendenciaEnderecoDestinatario($cliente) === null) {
            $cep = $this->digitos($cliente->cep ?: $empresa->cep);
            if (strlen($cep) !== 8) {
                $pendencias[] = 'Informe o CEP do cliente com 8 dígitos. Sem CEP do cliente, vale o CEP da empresa, que também precisa ter 8 dígitos.';
            }

            $ufCliente = strtoupper(trim((string) $cliente->estado));
            if ($ufCliente !== '' && $ufCliente !== strtoupper((string) $empresa->uf)) {
                $pendencias[] = 'O cliente é de outra UF; esta versão emite apenas operação interna.';
            } elseif (mb_strtolower(trim((string) $cliente->cidade)) !== mb_strtolower(trim((string) $empresa->cidade))) {
                $pendencias[] = 'O cliente está em outro município; esta versão só emite NF-e para a cidade da empresa.';
            }
        }

        return array_values(array_unique($pendencias));
    }

    /**
     * Datas que o XML recusaria. Vale antes de confirmar a venda e antes de reservar número.
     *
     * @return list<string>
     */
    public function pendenciasDeData(Venda $venda, Empresa $empresa): array
    {
        $pendencias = [];
        $datas = new NfeDatas;

        $fuso = $datas->fusoDaUf($empresa->uf);
        if ($datas->diaPosterior($venda->data_venda, $fuso)) {
            $pendencias[] = 'A data de emissão não pode ser posterior a hoje.';
        } elseif ($datas->diasDeAtraso($venda->data_venda, $fuso) >= NfeDatas::ATRASO_MAXIMO_DIAS) {
            $pendencias[] = 'A data de emissão está '.NfeDatas::ATRASO_MAXIMO_DIAS.' dias ou mais no passado. A SEFAZ rejeita NF-e emitida há mais de 30 dias (código 228): use uma data mais recente.';
        }

        if ($venda->data_saida && $venda->data_venda && $venda->data_saida->copy()->startOfDay()->lt($venda->data_venda->copy()->startOfDay())) {
            $pendencias[] = 'A data de saída não pode ser anterior à data de emissão.';
        }

        return $pendencias;
    }

    /**
     * Destinatário que a SEFAZ recusaria: sem CPF/CNPJ válido (237), sem endereço (726) ou não contribuinte
     * (indIEDest 9) marcado como "não é consumidor final" (696). Vale antes de reservar número.
     *
     * @return list<string>
     */
    public function pendenciasDeDestinatario(Venda $venda): array
    {
        $venda->loadMissing('cliente');
        $pendencias = [];

        if (($documento = NfeBuilderService::pendenciaDocumentoDestinatario($venda->cliente)) !== null) {
            $pendencias[] = $documento;
        } elseif (($endereco = NfeBuilderService::pendenciaEnderecoDestinatario($venda->cliente)) !== null) {
            $pendencias[] = $endereco;
        }

        if ($venda->ind_final !== null && (int) $venda->ind_final === 0 && NfeBuilderService::indIEDest($venda->cliente) === 9) {
            $pendencias[] = NfeBuilderService::MENSAGEM_NAO_CONTRIBUINTE;
        }

        return $pendencias;
    }

    /**
     * Alíquota cadastrada que esta versão não envia. Não bloqueia a emissão.
     *
     * @return list<string>
     */
    public function avisosAliquota(Venda $venda): array
    {
        $venda->loadMissing('itens.produto');
        $avisos = [];

        foreach ($venda->itens as $item) {
            $produto = $item->produto;
            if (! $produto) {
                continue;
            }

            $nome = '"'.$item->produto_nome.'"';
            foreach ([
                'aliquota_icms' => 'ICMS',
                'aliquota_pis' => 'PIS',
                'aliquota_cofins' => 'COFINS',
            ] as $campo => $imposto) {
                if ((float) $produto->{$campo} > 0) {
                    $avisos[] = "Item {$nome}: o produto tem alíquota de {$imposto} cadastrada, mas o XML desta versão envia o imposto zerado.";
                }
            }
        }

        return $avisos;
    }

    private function digitos(mixed $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }
}
